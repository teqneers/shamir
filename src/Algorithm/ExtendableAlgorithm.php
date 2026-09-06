<?php

namespace TQ\Shamir\Algorithm;

use OutOfRangeException;
use RuntimeException;

/**
 * Interface ExtendableAlgorithm
 *
 * An algorithm that can issue further shares for a secret that was already
 * divided, without the secret itself having to be supplied again.
 *
 * Kept separate from {@see Algorithm} so that existing implementations of that
 * interface keep working unchanged.
 *
 * @package TQ\Shamir\Algorithm
 */
interface ExtendableAlgorithm
{
    /**
     * Creates additional shares for a secret that has already been divided
     *
     * Requires at least `threshold` existing shares - enough, by definition, to
     * reconstruct the secret - so treat calling this as being as sensitive as
     * recovering the secret itself.
     *
     * The new shares continue the numbering after `$highestSequence`. That number
     * cannot be derived from `$keys`, which may be any subset of what was handed
     * out, so it has to be stated by the caller. Understating it re-issues a number
     * already in use; since the same polynomial is rebuilt, the result is a
     * byte-identical duplicate of the existing share rather than a conflicting
     * value, so recovery still never yields a wrong secret - combining a duplicate
     * with its twin raises "Repeated share detected". The cost is that two holders
     * unknowingly share one share.
     *
     * @param  array  $keys             At least `threshold` shares of one secret
     * @param  int    $additional       How many further shares to create
     * @param  int    $highestSequence  Highest share number ever issued for this secret
     *
     * @return array                    The additional shares
     * @throws OutOfRangeException
     * @throws RuntimeException
     */
    public function addShares(array $keys, int $additional, int $highestSequence): array;
}
