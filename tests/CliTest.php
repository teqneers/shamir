<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';

    protected $secretAscii;

    protected $descriptorSpec;

    /**
     * The CLI under test, invoked through a pinned interpreter
     *
     * The assertions below compare the command's stdout byte for byte, so they
     * are only meaningful if nothing else can write there. On the CLI SAPI
     * display_errors defaults to stdout, which means a deprecation raised by an
     * older symfony/console lands in the middle of the output and fails these
     * tests for reasons that have nothing to do with this library - and whether
     * that happens depends on the php.ini of whoever runs the suite.
     *
     * Pinning PHP_BINARY (rather than relying on the shebang's `env php`) and
     * routing any remaining diagnostics to stderr makes the contract explicit.
     * Deprecations from this library's own code are still reported, by PHPUnit,
     * which is the right place for them.
     */
    protected static function cmd(): string
    {
        return escapeshellarg(PHP_BINARY)
               .' -d error_reporting='.(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED)
               .' -d display_errors=stderr '
               .escapeshellarg(__DIR__.'/../bin/shamir.php');
    }

    protected function setUp(): void
    {
        $this->descriptorSpec = [
            1 => ["pipe", "w"], // stdout is a pipe that the child will write to
            2 => ["pipe", "w"], // stderr is a pipe that the child will write to
        ];
    }

    protected function execute($cmd): array
    {
        $ret = [];

        $process = proc_open($cmd, $this->descriptorSpec, $pipes);
        if (is_resource($process)) {
            $ret['std'] = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $ret['err'] = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $ret['ret'] = proc_close($process);
        }

        return $ret;
    }

    public static function provideUsage(): array
    {
        return [
            [self::cmd(), '.*Usage:.*'],
            [self::cmd(), '.*Available commands:.*'],
            [self::cmd().' help', '.*Usage:.*'],
            [self::cmd().' -h', '.*Usage:.*'],
            [self::cmd().' --help', '.*Usage:.*'],
            [self::cmd().' list', '.*Usage:.*'],
            [self::cmd().' list', '.*Available commands:.*'],
            [self::cmd().' list', '.*Available commands:.*'],
            [self::cmd().' help shamir:share', '.*Create a shared secret.*'],
            [self::cmd().' help shamir:recover', '.*Recover a shared secret.*'],
        ];
    }

    #[DataProvider('provideUsage')]
    public function testUsage($cmd, $regexp): void
    {
        $ret = $this->execute($cmd);

        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertMatchesRegularExpression('('.$regexp.')', $ret['std']);
        self::assertSame('', $ret['err']);
    }

    public function testWrongCommand(): void
    {
        $ret = $this->execute(self::cmd().' quatsch');

        self::assertEquals(1, $ret['ret']);
        self::assertSame('', $ret['std']);
        self::assertMatchesRegularExpression('(.*Command "quatsch" is not defined..*)', $ret['err']);
    }

    public function testUsageQuiet(): void
    {
        $ret = $this->execute(self::cmd().' help -q');

        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertSame('', $ret['std']);
        self::assertSame('', $ret['err']);
    }

    public function testVersion(): void
    {
        $ret = $this->execute(self::cmd().' -V');

        self::assertEquals(0, $ret['ret']);
        self::assertMatchesRegularExpression('(Shamir\'s Shared Secret CLI.*)', $ret['std']);
    }

    public function testFileInput(): void
    {
        $ret = $this->execute(self::cmd().' shamir:share -f tests/secret.txt');
        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
    }

    public function testStandardInput(): void
    {
        $ret = $this->execute('echo -n "Share my secret" | '.self::cmd().' shamir:share');
        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
    }

    /**
     * The secret argument must survive a STDIN that is at EOF rather than a terminal
     *
     * This is the documented `shamir:share "..."` usage running anywhere STDIN is
     * not interactive - cron, CI, `docker run` without -t. Such a STDIN selects as
     * readable but yields nothing, which used to be read as an empty secret, hide
     * the argument and end in a TypeError out of Secret::share().
     */
    public function testSecretArgumentIsUsedWhenStandardInputIsAtEof(): void
    {
        $ret = $this->execute(self::cmd().' shamir:share "Share my secret" < /dev/null');

        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertStringNotContainsString('Fatal error', $ret['err']);
        self::assertMatchesRegularExpression('(10201.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10202.*)', $ret['std']);
        self::assertMatchesRegularExpression('(10203.*)', $ret['std']);
        // the argument is still insecure, so the warning has to reach STDERR
        self::assertStringContainsString('insecure', $ret['err']);
    }

    /**
     * A share created from the argument has to round-trip back to the secret
     */
    public function testSecretArgumentRoundTrip(): void
    {
        $shared = $this->execute(self::cmd().' shamir:share -s 3 -t 2 "Share my secret" < /dev/null');
        self::assertEquals(0, $shared['ret'], 'Non zero return code: '.var_export($shared, true));

        $keys = preg_split('(\s+)', trim($shared['std']), -1, PREG_SPLIT_NO_EMPTY);
        self::assertCount(3, $keys);

        $ret = $this->execute(
            self::cmd().' shamir:recover '.escapeshellarg($keys[0]).' '.escapeshellarg($keys[2]).' < /dev/null'
        );

        self::assertEquals(0, $ret['ret'], 'Non zero return code: '.var_export($ret, true));
        self::assertStringContainsString('Share my secret', $ret['std']);
    }

    /**
     * Without a secret from any source the command has to fail, not crash
     */
    public function testMissingSecretFailsWithoutFatalError(): void
    {
        $ret = $this->execute(self::cmd().' shamir:share --no-interaction < /dev/null');

        self::assertEquals(1, $ret['ret'], 'Expected a failure exit code: '.var_export($ret, true));
        self::assertStringNotContainsString('Fatal error', $ret['err']);
        self::assertMatchesRegularExpression('(.*no secret given.*)', $ret['err']);
    }
}
