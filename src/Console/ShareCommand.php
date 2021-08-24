<?php
/**
 * Created by PhpStorm.
 * User: stefan
 * Date: 21.09.14
 * Time: 10:28
 */

namespace TQ\Shamir\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TQ\Shamir\Secret;
use UnexpectedValueException;

class ShareCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('shamir:share')
            ->setDescription('Create a shared secret')
            ->addArgument(
                'secret',
                InputArgument::OPTIONAL,
                'The secret to share',
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'File containing secret',
            )
            ->addOption(
                'shares',
                's',
                InputOption::VALUE_REQUIRED,
                'The number of shared secrets to generate',
                3,
            )
            ->addOption(
                'threshold',
                't',
                InputOption::VALUE_REQUIRED,
                'The minimum number of shared secrets required to recover',
                2,
            )
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (is_null($input->getOption('file'))) {
            $secret = $input->getArgument('secret');
        } else {
            $secret = $this->readFile($input->getOption('file'));
        }

        if (empty($secret)) {
            /** @var QuestionHelper $dialog */
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

        $shared = Secret::share($secret, $shares, $threshold);

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $block     = $formatter->formatBlock($shared, 'info', true);
        $output->writeln($block);

        return 0;
    }

    protected function readFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \Exception('The file "'.$file.'" is not readable.');
        }

        return file_get_contents($file);
    }
}
