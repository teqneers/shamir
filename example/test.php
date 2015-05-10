#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TQ\Shamir\Secret;

// create 5 shares with a threshold of 2, so you will need a minimum
// of 2 shares to recover the secret.
$shares = Secret::share('Shamir\'s Shared Secret Implementation in PHP', 5, 2);

var_dump($shares);

// we can use different keys to recover the data, but we need at least 2 of them
var_dump(Secret::recover(array_slice($shares, 0, 2)));
var_dump(Secret::recover(array_slice($shares, 1, 2)));


