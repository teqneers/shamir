<?php
/**
 * Created by PhpStorm.
 * User: stefan
 * Date: 21.09.14
 * Time: 10:28
 */

namespace TQ\Shamir\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TQ\Shamir\Secret;

class CreateCommand extends Command
{

    protected function configure()
    {
        $this->setName('shamir:create')->setDescription('Create a shared secret');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $dialog */
        $helper = $this->getHelper('question');

        $question = new Question('Please enter the secret to share: ');
        $secret = $helper->ask($input, $output, $question);

        $question = new Question('Please enter the number of shared secrets to create: ', 3);
        $number = $helper->ask($input, $output, $question);

        $question = new Question('Please enter the number of shared secrets required: ', 2);
        $quorum = $helper->ask($input, $output, $question);

        $shared = Secret::share($secret, $number, $quorum);
        $output->writeln('========================');
        foreach ($shared as $s) {
            $output->writeln($s);
        }
        $output->writeln('========================');
    }

}