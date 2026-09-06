<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Console\AddCommand;
use TQ\Shamir\Console\RecoverCommand;
use TQ\Shamir\Console\ShareCommand;
use TQ\Shamir\Secret;

/**
 * Drives the console commands in-process
 *
 * CliTest runs bin/shamir.php as a real subprocess, which is the only way to check
 * what the shipped binary actually does - and is how the STDIN handling bugs were
 * found. The cost is that none of that execution is visible to the coverage driver,
 * because it happens in another process.
 *
 * These tests cover the same classes from inside the test process, so the branches
 * are measured. Both layers earn their place: this one sees the code, CliTest sees
 * the program.
 *
 * @see CliTest
 */
class ConsoleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        Secret::setAlgorithm(new Shamir(), false);
        Secret::setRandomGenerator(null, false);
    }

    protected function tearDown(): void
    {
        Secret::setAlgorithm(new Shamir(), false);
        Secret::setRandomGenerator(null, false);
    }

    /**
     * Wires a command up far enough to be executed
     *
     * Going through setApplication() rather than Application::add() keeps this
     * working across the whole symfony/console range the package allows, since
     * add() was removed in 8.0 and its replacement does not exist before 7.4.
     */
    protected function tester(Command $command): CommandTester
    {
        $command->setApplication(new Application());

        return new CommandTester($command);
    }

    /**
     * Runs a command with the two output streams kept apart
     *
     * CommandTester folds STDERR into getDisplay() unless told otherwise, which would
     * mix diagnostics - the insecure-argument warning, error messages - into the
     * share output these tests parse.
     */
    protected function runCommand(CommandTester $tester, array $input = [], bool $interactive = false): int
    {
        return $tester->execute(
            $input,
            ['capture_stderr_separately' => true, 'interactive' => $interactive]
        );
    }

    /**
     * Pulls share strings out of command output, ignoring prompts and blank lines
     */
    protected function extractShares(string $display): array
    {
        $shares = [];
        foreach (preg_split('(\R)', $display) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('(^[0-9a-f][0-9a-z.,:;+*#%=-]{8,}$)', $line) === 1) {
                $shares[] = $line;
            }
        }

        return $shares;
    }

    /**
     * Splits a secret and returns the shares, using the command under test
     */
    protected function makeShares(string $secret, int $count = 3, int $threshold = 2): array
    {
        $tester = $this->tester(new ShareCommand());
        $this->runCommand($tester, ['secret' => $secret, '--shares' => $count, '--threshold' => $threshold]);

        return $this->extractShares($tester->getDisplay());
    }

    public function testShareWithSecretAsArgument(): void
    {
        $tester = $this->tester(new ShareCommand());
        $status = $this->runCommand($tester, ['secret' => 'in process', '--shares' => 4, '--threshold' => 2]);

        self::assertSame(Command::SUCCESS, $status);
        $keys = $this->extractShares($tester->getDisplay());
        self::assertCount(4, $keys);
        self::assertSame('in process', Secret::recover([$keys[0], $keys[3]]));
    }

    public function testShareWarnsAboutTheSecretOnTheCommandLine(): void
    {
        $tester = $this->tester(new ShareCommand());
        $this->runCommand($tester, ['secret' => 'visible in ps']);

        // the warning belongs on STDERR, so piping the shares stays clean
        self::assertStringContainsString('insecure', $tester->getErrorOutput());
        self::assertStringNotContainsString('insecure', $tester->getDisplay());
    }

    public function testShareReadsTheSecretFromAFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'shamir');
        file_put_contents($file, 'secret from a file');

        try {
            $tester = $this->tester(new ShareCommand());
            $status = $this->runCommand($tester, ['--file' => $file, '--shares' => 3, '--threshold' => 2]);

            self::assertSame(Command::SUCCESS, $status);
            $keys = $this->extractShares($tester->getDisplay());
            self::assertSame('secret from a file', Secret::recover(array_slice($keys, 0, 2)));
        } finally {
            unlink($file);
        }
    }

    /**
     * An unreadable file has to fail the command, not the process around it
     */
    public function testShareFailsOnAnUnreadableFile(): void
    {
        $tester = $this->tester(new ShareCommand());
        $status = $this->runCommand($tester, ['--file' => '/nonexistent/shamir-'.uniqid('', true)]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('not readable', $tester->getErrorOutput());
    }

    public function testShareAsksForEverythingInteractively(): void
    {
        $tester = $this->tester(new ShareCommand());
        $tester->setInputs(['interactive secret', '4', '2']);
        $status = $this->runCommand($tester, [], true);

        self::assertSame(Command::SUCCESS, $status);
        // QuestionHelper puts prompts on STDERR, so `shamir:share > keys.txt` still
        // yields a file of nothing but shares
        self::assertStringContainsString('The secret to share', $tester->getErrorOutput());

        $display = $tester->getDisplay();
        self::assertStringNotContainsString('The secret to share', $display);

        $keys = $this->extractShares($display);
        self::assertCount(4, $keys);
        self::assertSame('interactive secret', Secret::recover(array_slice($keys, 0, 2)));
    }

    public function testShareRejectsANonIntegerShareCount(): void
    {
        $tester = $this->tester(new ShareCommand());
        $tester->setInputs(['a secret', 'not a number']);

        $this->expectException(\UnexpectedValueException::class);
        $this->runCommand($tester, [], true);
    }

    public function testRecoverWithSharesAsArguments(): void
    {
        $keys   = $this->makeShares('recovered in process');
        $tester = $this->tester(new RecoverCommand());
        $status = $this->runCommand($tester, ['shares' => [$keys[0], $keys[2]]]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('recovered in process', $tester->getDisplay());
    }

    public function testRecoverAsksForSharesInteractively(): void
    {
        $keys   = $this->makeShares('asked for shares');
        $tester = $this->tester(new RecoverCommand());
        $tester->setInputs([$keys[0], $keys[1], '']);
        $status = $this->runCommand($tester, [], true);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('asked for shares', $tester->getDisplay());
    }

    public function testRecoverFailsWithoutAnyShares(): void
    {
        $tester = $this->tester(new RecoverCommand());
        $status = $this->runCommand($tester);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('no shares given', $tester->getErrorOutput());
    }

    public function testAddWithSharesAsArguments(): void
    {
        $keys   = $this->makeShares('extend in process', 3, 2);
        $tester = $this->tester(new AddCommand());
        $status = $this->runCommand($tester, ['existing' => [$keys[0], $keys[1]], '--highest' => 3, '--shares' => 2]);

        self::assertSame(Command::SUCCESS, $status);
        $new = $this->extractShares($tester->getDisplay());
        self::assertCount(2, $new);
        // works with the share that was never handed to the command
        self::assertSame('extend in process', Secret::recover([$keys[2], $new[0]]));
    }

    public function testAddReadsExistingSharesFromAFile(): void
    {
        $keys = $this->makeShares('extend from file', 3, 2);
        $file = tempnam(sys_get_temp_dir(), 'shamir');
        file_put_contents($file, $keys[0]."\n\n".$keys[1]."\n");

        try {
            $tester = $this->tester(new AddCommand());
            $status = $this->runCommand($tester, ['--file' => $file, '--highest' => 3]);

            self::assertSame(Command::SUCCESS, $status);
            $new = $this->extractShares($tester->getDisplay());
            self::assertCount(1, $new);
            self::assertSame('extend from file', Secret::recover([$keys[2], $new[0]]));
        } finally {
            unlink($file);
        }
    }

    public function testAddFailsOnAnUnreadableFile(): void
    {
        $tester = $this->tester(new AddCommand());
        $status = $this->runCommand($tester, ['--file' => '/nonexistent/shamir-'.uniqid('', true), '--highest' => 3]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('not readable', $tester->getErrorOutput());
    }

    public function testAddAsksForSharesAndTheHighestNumberInteractively(): void
    {
        $keys   = $this->makeShares('asked to extend', 3, 2);
        $tester = $this->tester(new AddCommand());
        $tester->setInputs([$keys[0], $keys[1], '', '3']);
        $status = $this->runCommand($tester, [], true);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Highest share number ever issued', $tester->getErrorOutput());
        self::assertCount(1, $this->extractShares($tester->getDisplay()));
    }

    public function testAddFailsWithoutTheHighestNumber(): void
    {
        $keys   = $this->makeShares('no highest', 3, 2);
        $tester = $this->tester(new AddCommand());
        $status = $this->runCommand($tester, ['existing' => [$keys[0], $keys[1]]]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('highest issued share number is required', $tester->getErrorOutput());
    }

    public function testAddFailsWithoutAnyShares(): void
    {
        $tester = $this->tester(new AddCommand());
        $status = $this->runCommand($tester, ['--highest' => 3]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('no shares given', $tester->getErrorOutput());
    }

    /**
     * A caller mistake is reported as an error rather than an exception trace
     */
    public function testAddReportsADomainErrorCleanly(): void
    {
        $keys   = $this->makeShares('understated', 3, 2);
        $tester = $this->tester(new AddCommand());
        $status = $this->runCommand($tester, ['existing' => [$keys[0], $keys[1]], '--highest' => 1]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString(
            'Highest issued share number has to be at least',
            $tester->getErrorOutput()
        );
    }

    public function testAddRejectsANonIntegerHighestNumber(): void
    {
        $keys   = $this->makeShares('bad highest', 3, 2);
        $tester = $this->tester(new AddCommand());
        $tester->setInputs(['not a number']);

        $this->expectException(\UnexpectedValueException::class);
        $this->runCommand($tester, ['existing' => [$keys[0], $keys[1]]], true);
    }
}
