<?php

namespace TQ\Shamir\Tests;

use RuntimeException;
use TQ\Shamir\Algorithm\Algorithm;

/**
 * An Algorithm that does not implement ExtendableAlgorithm
 *
 * Exists so the facade can be checked for reporting that case properly, and to
 * document that implementing Algorithm alone is still enough - adding shares is
 * an optional capability rather than part of the base contract.
 */
class PlainAlgorithm implements Algorithm
{
    public function share(string $secret, int $shares, int $threshold = 2): array
    {
        throw new RuntimeException('Not needed for this test double.');
    }

    public function recover(array $keys): string
    {
        throw new RuntimeException('Not needed for this test double.');
    }
}
