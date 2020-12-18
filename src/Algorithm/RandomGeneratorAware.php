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
     *
     * @param  Generator  $generator
     */
    public function setRandomGenerator(Generator $generator);

    /**
     * Returns the random generator
     *
     * @return Generator
     */
    public function getRandomGenerator(): Generator;
}
