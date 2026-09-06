<?php

namespace TQ\Shamir\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use OutOfRangeException;
use RuntimeException;
use TQ\Shamir\Secret;
use UnexpectedValueException;

class AddCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('shamir:add')
            ->setDescription('Add shares to an existing shared secret')
            ->setHelp(
                <<<'HELP'
                Creates further shares for a secret that has already been divided, without
                needing the secret itself.

                It takes at least <comment>threshold</comment> of the existing shares, which is by
                definition enough to reconstruct the secret - so this is as sensitive an
                operation as <info>shamir:recover</info>.

                <comment>--highest</comment> is the highest share number ever handed out for this secret.
                It cannot be worked out from the shares you supply, because those may be any
                subset of them. Understating it re-creates a share that is already in
                circulation: recovery still never returns a wrong secret, but two people end
                up holding the same share.

                  <info>%command.full_name% -H 5 "10201..." "10202..."</info>
                  <info>%command.full_name% -H 5 -s 3 -f shares.txt</info>
                HELP
            )
            ->addArgument(
                'existing',
                InputArgument::IS_ARRAY,
                'Existing shares of the secret'
            )
            ->addOption(
                'shares',
                's',
                InputOption::VALUE_OPTIONAL,
                'The number of additional shares to create',
                1
            )
            ->addOption(
                'highest',
                'H',
                InputOption::VALUE_OPTIONAL,
                'Highest share number ever issued for this secret'
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'File containing the existing shares, one per line'
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $existing = $this->readExisting($input, $output);

        if ($existing === null) {
            // readExisting() already explained what went wrong
            return Command::FAILURE;
        }

        if (empty($existing)) {
            $this->errorOutput($output)->writeln('<error>ERROR: no shares given.</error>');

            return Command::FAILURE;
        }

        $highest = $input->getOption('highest');
        if ($highest === null) {
            $highest = $this->askHighest($input, $output);
        }

        if (!is_numeric($highest)) {
            $this->errorOutput($output)->writeln(
                '<error>ERROR: the highest issued share number is required. '
                .'Pass --highest, or answer the prompt.</error>'
            );

            return Command::FAILURE;
        }

        try {
            $added = Secret::addShares($existing, (int)$input->getOption('shares'), (int)$highest);
        } catch (OutOfRangeException | RuntimeException $e) {
            // these report a caller mistake - a stack trace helps nobody
            $this->errorOutput($output)->writeln('<error>ERROR: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $output->writeln($formatter->formatBlock($added, 'info'));

        return Command::SUCCESS;
    }

    /**
     * Collects the existing shares from the arguments, a file, or the prompt
     *
     * @return array|null  null when the source itself failed and was reported
     */
    protected function readExisting(InputInterface $input, OutputInterface $output): ?array
    {
        /** @var array $existing */
        $existing = $input->getArgument('existing');
        if (!empty($existing)) {
            return $existing;
        }

        $file = $input->getOption('file');
        if ($file !== null) {
            if (!is_readable($file)) {
                $this->errorOutput($output)->writeln('<error>ERROR: file "'.$file.'" is not readable.</error>');

                return null;
            }

            $lines = preg_split('(\R)', (string)file_get_contents($file)) ?: [];

            return array_values(array_filter(array_map('trim', $lines), static fn($l) => $l !== ''));
        }

        /** @var QuestionHelper $helper */
        $helper   = $this->getHelper('question');
        $question = new Question('<question>Existing share</question> <comment>[empty to stop]</comment>: ');

        // ask() yields null at end of input and under --no-interaction
        while (($share = $helper->ask($input, $output, $question)) !== null) {
            $share = trim($share);
            if ($share === '') {
                break;
            }
            $existing[] = $share;
        }

        return $existing;
    }

    /**
     * Asks for the highest share number ever issued
     *
     * Deliberately offers no default: guessing it low quietly re-issues a share that
     * somebody already holds, which is the one thing the caller has to get right.
     *
     * @return string|null
     */
    protected function askHighest(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper   = $this->getHelper('question');
        $question = new Question(
            '<question>Highest share number ever issued for this secret</question>: '
        );
        $question->setValidator(
            static function ($a) {
                if ($a !== null && !is_int($a) && !ctype_digit((string)$a)) {
                    throw new UnexpectedValueException('The highest issued share number must be an integer');
                }

                return $a;
            }
        );

        return $helper->ask($input, $output, $question);
    }

    /**
     * Returns the stream to write diagnostics to, keeping them out of STDOUT
     */
    protected function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
