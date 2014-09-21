<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TQ\Shamir\Secret;


$secret = new Secret();
$shares = $secret->share('Shamir\'s Shared Secret Implementation in PHP', 3, 2);
var_dump($shares);
var_dump(Secret::recover(array_slice($shares, 0, 2)));
