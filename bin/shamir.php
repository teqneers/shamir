#!/usr/bin/env php
<?php
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use TQ\Shamir\Console\RecoverCommand;
use TQ\Shamir\Console\ShareCommand;

$command     = new ShareCommand();
$application = new Application('Shamir\'s Shared Secret CLI', '1.1.0');
$application->add(new RecoverCommand());
$application->add(new ShareCommand());
$application->run();
