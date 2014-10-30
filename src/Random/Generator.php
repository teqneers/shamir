<?php

namespace TQ\Shamir\Random;

/**
 * Interface Generator
 *
 * @package TQ\Shamir\Random
 */
interface Generator
{
    /**
     * Returns a random number
     *
     * @return int
     */
    public function getRandomInt();
}