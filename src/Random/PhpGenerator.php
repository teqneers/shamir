<?php

namespace TQ\Shamir\Random;

use RuntimeException;

/**
 * Class PhpGenerator
 *
 * @package TQ\Shamir\Random
 */
class PhpGenerator implements Generator
{
    /**
     * The maximum random number
     *
     * @var int
     */
    protected $max = PHP_INT_MAX;

    /**
     * The minimum random number
     *
     * @var int
     */
    protected $min = 1;

    /**
     * Constructor
     *
     * @param  int  $max  The maximum random number
     * @param  int  $min  The minimum random number (must be positive)
     */
    public function __construct($max = PHP_INT_MAX, $min = 1)
    {
        if((int)$min < 1) {
            throw new \OutOfRangeException('The min number must be a positive integer.');
        }

        $this->min = (int)$min;
        $this->max = (int)$max;
    }

    /**
     * @inheritdoc
     */
    public function getRandomInt()
    {
        try {
            $random = random_int($this->min, $this->max);
        } catch (\Exception $e) {
            throw new RuntimeException(
                'Random number generator algorithm failed.', 0, $e
            );
        }

        return $random;
    }
}
