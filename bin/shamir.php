#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use TQ\Shamir\Console\CreateCommand;

$command     = new CreateCommand();
$application = new Application('Shamir\'s Shared Secret CLI', '0.1.0');
$application->add($command);
$application->setDefaultCommand($command->getName());
$application->run();