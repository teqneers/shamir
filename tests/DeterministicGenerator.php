<?php

namespace TQ\Shamir\Tests;

use TQ\Shamir\Random\Generator;

/**
 * A reproducible stand-in for the random generator
 *
 * Sharing is randomised, so identical input normally yields different shares and
 * the exact encoding cannot be asserted. Injecting this makes Secret::share()
 * deterministic, which is what lets the known-answer vectors pin the format byte
 * for byte. It is a plain LCG - never use it for anything but tests.
 */
class DeterministicGenerator implements Generator
{
    protected $state;

    public function __construct(int $seed = 20151123)
    {
        $this->state = $seed;
    }

    public function getRandomInt()
    {
        // stays well inside 63 bits, so PHP 7.2 and 8.5 agree on the result
        $this->state = ($this->state * 1103515245 + 12345) % 2147483648;

        return $this->state + 1;
    }
}
