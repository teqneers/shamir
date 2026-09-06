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
use RuntimeException;
use TQ\Shamir\Secret;
use UnexpectedValueException;

class ShareCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('shamir:share')->setDescription('Create a shared secret')->addArgument(
            'secret',
            InputArgument::OPTIONAL,
            'The secret to share'
        )->addOption(
            'file',
            'f',
            InputOption::VALUE_OPTIONAL,
            'File containing secret'
        )->addOption(
            'shares',
            's',
            InputOption::VALUE_OPTIONAL,
            'The number of shared secrets to generate',
            3
        )->addOption(
            'threshold',
            't',
            InputOption::VALUE_OPTIONAL,
            'The minimum number of shared secrets required to recover',
            2
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $secret = $this->readFile($input, $output);
        } catch (RuntimeException $e) {
            $this->errorOutput($output)->writeln('<error>ERROR: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        if ($secret === null) {
            $secret = $input->getArgument('secret');
            if (!empty($secret)) {
                $this->errorOutput($output)->writeln(
                    '<comment>Warning: passing the secret as a command argument is insecure. '
                    .'The secret may be visible to other users via process listings (e.g. `ps aux`). '
                    .'Use --file, STDIN, or the interactive prompt instead.</comment>'
                );
            }
        }

        if (empty($secret)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            $question = new Question('<question>The secret to share</question>: ');
            $secret   = $helper->ask($input, $output, $question);

            $question = new Question(
                '<question>Number of shared secrets to create</question> <comment>[3]</comment>: ', 3
            );
            $question->setValidator(
                static function ($a) {
                    if (!is_int($a) && !ctype_digit($a)) {
                        throw new UnexpectedValueException('The number of shared secrets must be an integer');
                    }

                    return (int)$a;
                }
            );
            $shares = $helper->ask($input, $output, $question);

            $question = new Question(
                '<question>Number of shared secrets required</question> <comment>[2]</comment>: ', 2
            );
            $question->setValidator(
                function ($a) {
                    if (!is_int($a) && !ctype_digit($a)) {
                        throw new UnexpectedValueException('The number of shared secrets required must be an integer');
                    }

                    return (int)$a;
                }
            );
            $threshold = $helper->ask($input, $output, $question);
        } else {
            $shares    = $input->getOption('shares');
            $threshold = $input->getOption('threshold');
        }

        // Both the interactive prompt and --no-interaction can leave us without a
        // secret; without this the null reaches Secret::share() as a TypeError.
        if (!is_string($secret) || $secret === '') {
            $this->errorOutput($output)->writeln('<error>ERROR: no secret given.</error>');

            return Command::FAILURE;
        }

        $shared = Secret::share($secret, $shares, $threshold);

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $block     = $formatter->formatBlock($shared, 'info');
        $output->writeln($block);

        return 0;
    }

    /**
     * Check STDIN or file option for input of secret
     */
    protected function readFile(InputInterface $input, OutputInterface $output): ?string
    {
        $secret = null;

        $file = $input->getOption('file');

        if ($file !== null) {
            # check for secret in file
            if (!is_readable($file)) {
                // reported by execute(), which turns it into an exit code - calling
                // exit() here would take down whatever process embeds the command
                throw new RuntimeException('file "'.$file.'" is not readable.');
            }

            $secret = file_get_contents($file);
        } else {
            # check if data is given by STDIN
            $readStreams   = [STDIN];
            $writeStreams  = [];
            $exceptStreams = [];
            $streamCount   = stream_select($readStreams, $writeStreams, $exceptStreams, 0);

            if ($streamCount === 1) {
                while (!feof(STDIN)) {
                    $secret .= fread(STDIN, 1024);
                }

                // A STDIN that is already at EOF - cron, CI, a pipeline that
                // produced nothing, `docker run` without -t, `< /dev/null` -
                // still selects as readable but yields no bytes. Returning ''
                // here would read as "the secret is an empty string" and mask
                // the secret argument, so report "nothing on STDIN" instead.
                if ($secret === '') {
                    $secret = null;
                }
            }
        }

        return $secret;
    }

    /**
     * Returns the stream to write diagnostics to, keeping them out of STDOUT
     */
    protected function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
