#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TQ\Shamir\Secret;

$shares = Secret::share('Shamir\'s Shared Secret Implementation in PHP', 5, 2);

var_dump($shares);

var_dump(Secret::recover(array_slice($shares, 0, 2)));
var_dump(Secret::recover(array_slice($shares, 1, 3)));


