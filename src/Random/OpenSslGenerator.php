<?php

namespace TQ\Shamir\Random;

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
     * @param int $bytes Bytes to use in result
     * @param int $forceStrong Force strong random number generation
     */
    public function __construct($bytes = PHP_INT_SIZE, $forceStrong = true)
    {
        $this->bytes = (int)$bytes;
        $this->forceStrong = (bool)$forceStrong;
    }

    /**
     * @inheritdoc
     * @see http://php.net/manual/en/function.openssl-random-pseudo-bytes.php
     * @throws \RuntimeException
     */
    public function getRandomInt()
    {
        $random = openssl_random_pseudo_bytes($this->bytes, $strong);
        if ($random === null || ($this->forceStrong && $strong !== true)) {
            throw new \RuntimeException(
                'Random number generator algorithm didn\'t used "cryptographically strong" method.'
            );
        }

        return hexdec(bin2hex($random));
    }

}