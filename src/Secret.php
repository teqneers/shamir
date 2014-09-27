<?php

namespace TQ\Shamir;

use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\RandomGeneratorAware;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Random\PhpGenerator;

/**
 * Class Secret
 *
 * This is a simple static facade to Shamir's shared secret algorithm
 *
 * @package TQ\Shamir
 */
class Secret
{
    /**
     * The random generator
     *
     * @var Generator|null
     */
    protected static $randomGenerator;

    /**
     * The algorithm
     *
     * @var Algorithm|null
     */
    protected static $algorithm;

    /**
     * Initializes the random generator
     *
     * @param Generator|null $randomGenerator The random generator
     */
    public static function initRandomGenerator(Generator $randomGenerator = null)
    {
        self::$randomGenerator = $randomGenerator;

        $algorithm = self::$algorithm;
        if ($algorithm instanceof RandomGeneratorAware) {
            $algorithm->setRandomGenerator(self::getRandomGenerator());
        }
    }

    /**
     * Returns the random generator
     *
     * @return Generator
     */
    protected static function getRandomGenerator()
    {
        if (!self::$randomGenerator) {
            self::$randomGenerator = new PhpGenerator();
        }

        return self::$randomGenerator;
    }

    /**
     * Returns the algorithm
     *
     * @return Algorithm
     */
    protected static function getAlgorithm()
    {
        if (!self::$algorithm) {
            self::setAlgorithm(new Shamir());
        }

        return self::$algorithm;
    }

    /**
     * Overrides the algorithm to use
     *
     * @param Algorithm $algorithm
     */
    public static function setAlgorithm(Algorithm $algorithm = null)
    {
        if ($algorithm instanceof RandomGeneratorAware) {
            $algorithm->setRandomGenerator(self::getRandomGenerator());
        }
        self::$algorithm = $algorithm;
    }

    /**
     * Generate shared secrets
     *
     * @param    string  $secret    Secret
     * @param    integer $shares    Number of parts to share
     * @param    integer $threshold Minimum number of shares required for decryption
     * @return  array               Secret shares
     * @throws \OutOfBoundsException
     */
    public static function share($secret, $shares, $threshold = 2)
    {
        return self::getAlgorithm()->share($secret, $shares, $threshold);
    }

    /**
     * Recovers the secret from the given shared keys
     *
     * @param array $keys
     * @return string
     */
    public static function recover(array $keys)
    {
        return self::getAlgorithm()->recover($keys);
    }
}