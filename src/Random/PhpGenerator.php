<?php

namespace TQ\Shamir\Random;

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
     * @param int $max The maximum random number
     * @param int $min The minimum random number
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
        $random = mt_rand($this->min, $this->max);
        if ($random === false ) {
            throw new \RuntimeException(
                'Random number generator algorithm failed.'
            );

        }
        return $random;
    }

}