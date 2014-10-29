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
    protected $min = 0;

    /**
     * Constructor
     *
     * @param int $max The maximum random number
     * @param int $min The minimum random number
     */
    public function __construct($max = PHP_INT_MAX, $min = 0)
    {
        $this->max = (int)$max;
        $this->min = (int)$min;
    }

    /**
     * @inheritdoc
     */
    public function getRandomInt()
    {
        return mt_rand(0, $this->max);
    }

}