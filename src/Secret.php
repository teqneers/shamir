<?php

namespace TQ\Shamir;

use OutOfBoundsException;
use OutOfRangeException;
use RuntimeException;
use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\ExtendableAlgorithm;
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
     * Overrides the random generator to use
     *
     * @param  Generator|null  $randomGenerator  The random generator
     * @param  boolean         $returnOld        True to return the old random generator
     *
     * @return  Generator|null The old random generator if $returnOld is true
     */
    public static function setRandomGenerator(?Generator $randomGenerator = null, $returnOld = true)
    {
        if ($returnOld) {
            $oldRandomGenerator = self::getRandomGenerator();
        } else {
            $oldRandomGenerator = null;
        }
        self::$randomGenerator = $randomGenerator;

        $algorithm = self::$algorithm;
        if ($algorithm instanceof RandomGeneratorAware) {
            $algorithm->setRandomGenerator(self::getRandomGenerator());
        }

        return $oldRandomGenerator;
    }

    /**
     * Returns the random generator
     *
     * @return  Generator
     */
    public static function getRandomGenerator()
    {
        if (!self::$randomGenerator) {
            self::$randomGenerator = new PhpGenerator();
        }

        return self::$randomGenerator;
    }

    /**
     * Returns the algorithm
     */
    public static function getAlgorithm(): ?Algorithm
    {
        if (!self::$algorithm) {
            self::setAlgorithm(new Shamir(), false);
        }

        return self::$algorithm;
    }

    /**
     * Overrides the algorithm to use
     *
     * @param  Algorithm|null  $algorithm
     * @param  boolean         $returnOld  True to return the old algorithm
     *
     * @return  Algorithm|null The old algorithm if $returnOld is true
     */
    public static function setAlgorithm(?Algorithm $algorithm = null, $returnOld = true): ?Algorithm
    {
        if ($returnOld) {
            $oldAlgorithm = self::getAlgorithm();
        } else {
            $oldAlgorithm = null;
        }

        if ($algorithm instanceof RandomGeneratorAware) {
            $algorithm->setRandomGenerator(self::getRandomGenerator());
        }
        self::$algorithm = $algorithm;

        return $oldAlgorithm;
    }

    /**
     * Generate shared secrets
     *
     * @param  string  $secret     Secret
     * @param  int     $shares     Number of parts to share
     * @param  int     $threshold  Minimum number of shares required for decryption
     *
     * @return  array              Secret shares
     * @throws  OutOfBoundsException
     */
    public static function share(string $secret, int $shares, int $threshold = 2): array
    {
        return self::getAlgorithm()->share($secret, $shares, $threshold);
    }

    /**
     * Recovers the secret from the given shared keys
     */
    public static function recover(array $keys): string
    {
        return self::getAlgorithm()->recover($keys);
    }

    /**
     * Creates additional shares for a secret that was already divided
     *
     * Needs at least `threshold` of the existing shares, which is by definition
     * enough to reconstruct the secret - so this is as sensitive an operation as
     * recovering it. `$highestSequence` is the highest share number ever handed
     * out for this secret; see ExtendableAlgorithm::addShares() for why it cannot
     * be inferred from `$keys`.
     *
     * @param  array  $keys             At least `threshold` shares of one secret
     * @param  int    $additional       How many further shares to create
     * @param  int    $highestSequence  Highest share number ever issued for this secret
     *
     * @return array                    The additional shares
     * @throws RuntimeException         If the algorithm cannot do this, or too few shares were given
     * @throws OutOfRangeException      If the counts do not fit the shares supplied
     */
    public static function addShares(array $keys, int $additional, int $highestSequence): array
    {
        $algorithm = self::getAlgorithm();

        if (!$algorithm instanceof ExtendableAlgorithm) {
            throw new RuntimeException(
                'The configured algorithm cannot add shares to an already divided secret.'
            );
        }

        return $algorithm->addShares($keys, $additional, $highestSequence);
    }
}
