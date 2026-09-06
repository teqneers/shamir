#!/usr/bin/env php
<?php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use TQ\Shamir\Console\RecoverCommand;
use TQ\Shamir\Console\ShareCommand;

$application = new Application('Shamir\'s Shared Secret CLI', '2.0.0');

foreach ([new RecoverCommand(), new ShareCommand()] as $command) {
    // Application::add() was deprecated in Symfony 7.4 and removed in 8.0 in
    // favour of addCommand(), which does not exist before 7.4. Support both so
    // the CLI works across the whole symfony/console range this package allows.
    if (method_exists($application, 'addCommand')) {
        $application->addCommand($command);
    } else {
        $application->add($command);
    }
}

$application->run();
