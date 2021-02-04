<?php

namespace TQ\Shamir\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TQ\Shamir\Secret;

class RecoverCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('shamir:recover')->setDescription('Recover a shared secret')->addArgument(
            'shares',
            InputArgument::IS_ARRAY,
            'Add the shared secrets'
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array $shares */
        $shares = $input->getArgument('shares');
        if (empty($shares)) {
            /** @var QuestionHelper $dialog */
            $helper   = $this->getHelper('question');
            $question = new Question('<question>Shared secret</question> <comment>[empty to stop]</comment>: ');
            $shares   = [];
            while (($share = trim($helper->ask($input, $output, $question))) !== '') {
                $shares[] = $share;
            }
        }

        $shared = Secret::recover($shares);

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $block     = $formatter->formatBlock($shared, 'info', true);
        $output->writeln($block);

        return 0;
    }
}
