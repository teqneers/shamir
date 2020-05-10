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
     * @param  integer  $max  The maximum random number
     * @param  integer  $min  The minimum random number
     */
    public function __construct($max = PHP_INT_MAX, $min = 1)
    {
        $this->min = (int)$min;
        $this->max = (int)$max;
    }

    /**
     * @inheritdoc
     */
    public function getRandomInt()
    {
        if (version_compare(PHP_VERSION, '7.0') >= 0) {
            $random = random_int($this->min, $this->max);
        } else {
            $random = mt_rand($this->min, $this->max);
        }

        if ($random === false) {
            throw new RuntimeException(
                'Random number generator algorithm failed.'
            );
        }

        return $random;
    }
}
