<?php

namespace TQ\Shamir\Algorithm;

use OutOfRangeException;
use RuntimeException;

/**
 * Interface Algorithm
 *
 * @package TQ\Shamir\Algorithm
 */
interface Algorithm
{
    /**
     * Generate shared secrets
     *
     * @param  string  $secret     Secret
     * @param  int     $shares     Number of parts to share
     * @param  int     $threshold  Minimum number of shares required for decryption
     *
     * @return  array               Secret shares
     * @throws OutOfRangeException
     */
    public function share($secret, $shares, $threshold = 2): array;

    /**
     * Recovers the secret from the given shared keys
     *
     * @param  array  $keys
     *
     * @return  string
     * @throws RuntimeException
     */
    public function recover(array $keys): string;
}
