<?php

namespace TQ\Shamir\Algorithm;

use TQ\Shamir\Random\Generator;

/**
 * Interface RandomGeneratorAware
 *
 * @package TQ\Shamir\Algorithm
 */
interface RandomGeneratorAware
{
    /**
     * Sets the random generator
     */
    public function setRandomGenerator(Generator $generator);

    /**
     * Returns the random generator
     */
    public function getRandomGenerator(): Generator;
}
