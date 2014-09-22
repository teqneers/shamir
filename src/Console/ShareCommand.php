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

class ShareCommand extends Command
{

    protected function configure()
    {
        $this->setName('shamir:share')->setDescription('Create a shared secret')->addArgument(
            'secret',
            InputArgument::OPTIONAL,
            'The secret to share'
        )->addOption(
            'number',
            null,
            InputOption::VALUE_OPTIONAL,
            'The number of shared secrets to generate',
            3
        )->addOption(
            'quorum',
            null,
            InputOption::VALUE_OPTIONAL,
            'The number of shared secrets required to recover',
            2
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $secret = $input->getArgument('secret');
        if (empty($secret)) {
            /** @var QuestionHelper $dialog */
            $helper = $this->getHelper('question');

            $question = new Question('<question>The secret to share</question>: ');
            $secret   = $helper->ask($input, $output, $question);

            $question = new Question(
                '<question>Number of shared secrets to create</question> <comment>[3]</comment>: ',
                3
            );
            $question->setValidator(
                function ($a) {
                    if (!is_int($a) && !ctype_digit($a)) {
                        throw new \Exception('The number of shared secrets must be an integer');
                    }

                    return (int)$a;
                }
            );
            $number = $helper->ask($input, $output, $question);

            $question = new Question(
                '<question>Number of shared secrets required</question> <comment>[2]</comment>: ',
                2
            );
            $question->setValidator(
                function ($a) {
                    if (!is_int($a) && !ctype_digit($a)) {
                        throw new \Exception('The number of shared secrets required must be an integer');
                    }

                    return (int)$a;
                }
            );
            $quorum = $helper->ask($input, $output, $question);
        } else {
            $number = $input->getOption('number');
            $quorum = $input->getOption('quorum');
        }


        $shared = Secret::share($secret, $number, $quorum);

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $block     = $formatter->formatBlock($shared, 'info', true);
        $output->writeln($block);
    }

}