<?php

namespace TQ\Shamir\Random;

use OutOfRangeException;
use RuntimeException;

/**
 * Class OpenSslGenerator
 *
 * Generate a pseudo-random string of bytes using the OpenSSL library.
 *
 * @package TQ\Shamir\Random
 */
class OpenSslGenerator implements Generator
{
    /**
     * Length of the desired string of bytes
     *
     * @var int
     */
    protected $bytes = PHP_INT_SIZE;

    /**
     * Force strong random number generation or "die"
     *
     * @var bool
     */
    protected $forceStrong = true;

    /**
     * Constructor
     *
     * @param  int   $bytes        Bytes to use in result
     * @param  bool  $forceStrong  Force strong random number generation
     */
    public function __construct(int $bytes = PHP_INT_SIZE, bool $forceStrong = true)
    {
        if($bytes < 1) {
            throw new OutOfRangeException('The length of the desired string of bytes. Must be a positive integer.');
        }

        $this->bytes       = $bytes;
        $this->forceStrong = $forceStrong;
    }

    /**
     * @return bool
     */
    public function isForceStrong(): bool
    {
        return $this->forceStrong;
    }

    /**
     * @inheritdoc
     * @see https://php.net/manual/en/function.openssl-random-pseudo-bytes.php
     * @throws RuntimeException
     */
    public function getRandomInt()
    {
        $random = openssl_random_pseudo_bytes($this->bytes, $strong);
        if ($random === false || ($this->forceStrong && $strong !== true)) {
            throw new RuntimeException(
                'Random number generator algorithm didn\'t used "cryptographically strong" method.'
            );
        }

        return hexdec(bin2hex($random));
    }
}
