#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TQ\Shamir\Secret;

//$shares = Secret::share('Shamir\'s Shared Secret Implementation in PHP', 300, 2);
$shares = Secret::share('ABCDE', 280, 2);
var_dump(array_slice($shares, 0, 10));
var_dump(Secret::recover(array_slice($shares, 0, 2)));
var_dump(Secret::recover(array_slice($shares, 1, 3)));
