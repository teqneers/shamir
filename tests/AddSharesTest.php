<?php

namespace TQ\Shamir\Tests;

use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Secret;

/**
 * Covers issuing further shares for an already divided secret
 *
 * @see \TQ\Shamir\Algorithm\ExtendableAlgorithm
 */
class AddSharesTest extends TestCase
{
    protected function setUp(): void
    {
        Secret::setAlgorithm(new Shamir(), false);
    }

    protected function tearDown(): void
    {
        Secret::setAlgorithm(new Shamir(), false);
        Secret::setRandomGenerator(null, false);
    }

    /**
     * The scenario from issue #3, step by step
     */
    public function testAddsAShareThatWorksWithTheOriginalOnes(): void
    {
        $secret = 'foo';
        $shares = Secret::share($secret, 5, 2);

        $added = Secret::addShares($shares, 1, 5);
        self::assertCount(1, $added);
        self::assertNotContains($added[0], $shares, 'The new share duplicates an existing one.');

        // the issue asks specifically for s5 + s6
        self::assertSame($secret, Secret::recover([$shares[4], $added[0]]));

        // and it has to hold for every other original share too
        foreach ($shares as $i => $share) {
            self::assertSame(
                $secret,
                Secret::recover([$share, $added[0]]),
                'New share does not combine with original share '.($i + 1).'.'
            );
        }

        // the originals keep working among themselves
        self::assertSame($secret, Secret::recover([$shares[0], $shares[3]]));
    }

    /**
     * Several shares can be added at once, and they work with each other
     */
    public function testAddsSeveralSharesAtOnce(): void
    {
        $secret = 'Lorem ipsum ünïcödé ✓';
        $shares = Secret::share($secret, 4, 3);

        $added = Secret::addShares($shares, 3, 4);
        self::assertCount(3, $added);
        self::assertSame($added, array_unique($added), 'Added shares are not distinct.');

        // three new shares alone satisfy the threshold of three
        self::assertSame($secret, Secret::recover($added));

        // and they mix freely with the originals
        self::assertSame($secret, Secret::recover([$shares[0], $added[0], $added[2]]));
        self::assertSame($secret, Secret::recover([$shares[1], $shares[3], $added[1]]));
    }

    /**
     * Only `threshold` shares are needed to issue more, not all of them
     */
    public function testAddsSharesFromAMinimalSubset(): void
    {
        $secret = 'quorum only';
        $shares = Secret::share($secret, 6, 2);

        // hold just two of the six
        $added = Secret::addShares([$shares[1], $shares[4]], 2, 6);

        // the new shares still agree with the four we never looked at
        foreach ([0, 2, 3, 5] as $i) {
            self::assertSame($secret, Secret::recover([$shares[$i], $added[0]]));
            self::assertSame($secret, Secret::recover([$shares[$i], $added[1]]));
        }
    }

    /**
     * Extending an extended set keeps working
     */
    public function testAddedSharesCanThemselvesBeExtended(): void
    {
        $secret = 'chained';
        $shares = Secret::share($secret, 3, 2);

        $first  = Secret::addShares($shares, 1, 3);
        $second = Secret::addShares([$shares[0], $first[0]], 1, 4);

        self::assertSame($secret, Secret::recover([$first[0], $second[0]]));
        self::assertSame($secret, Secret::recover([$shares[2], $second[0]]));
    }

    /**
     * Chunk sizes above one byte encode the same way for added shares
     */
    public function testAddsSharesWhenChunkSizeIsLargerThanOneByte(): void
    {
        $secret = 'wide chunks';
        $shares = Secret::share($secret, 300, 2);

        $added = Secret::addShares([$shares[0], $shares[299]], 2, 300);

        self::assertSame('2', $added[0][0], 'Added share does not carry the two byte chunk size.');
        self::assertSame(strlen($shares[0]), strlen($added[0]), 'Added share differs in length.');
        self::assertSame($secret, Secret::recover([$shares[150], $added[0]]));
        self::assertSame($secret, Secret::recover([$added[0], $added[1]]));
    }

    public static function provideSecrets(): array
    {
        return [
            'single byte'        => ['x', 3, 2],
            'needs padding'      => ['odd', 4, 2],
            'threshold equals n' => ['all of them', 3, 3],
            'utf-8'              => ['ünïcödé ✓ 問ナマ業', 5, 3],
            'long'               => [str_repeat('long secret ', 20), 4, 2],
        ];
    }

    #[DataProvider('provideSecrets')]
    public function testAddedShareRecoversAcrossShapes(string $secret, int $count, int $threshold): void
    {
        $shares = Secret::share($secret, $count, $threshold);
        $added  = Secret::addShares($shares, 1, $count);

        $combination   = array_slice($shares, 0, $threshold - 1);
        $combination[] = $added[0];

        self::assertSame($secret, Secret::recover($combination));
        self::assertSame(strlen($shares[0]), strlen($added[0]));
    }

    /**
     * Shares written by an earlier release can still be extended
     */
    public function testExtendsSharesCreatedByAnOlderVersion(): void
    {
        $fixtures = json_decode(
            file_get_contents(__DIR__.'/fixtures/legacy-shares.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($fixtures as $fixture) {
            $shares = array_slice($fixture['shares'], 0, $fixture['threshold']);
            $added  = Secret::addShares($shares, 1, $fixture['shareCount']);

            $combination   = array_slice($shares, 0, $fixture['threshold'] - 1);
            $combination[] = $added[0];

            self::assertSame(
                $fixture['secret'],
                Secret::recover($combination),
                'Could not extend shares from '.$fixture['createdBy'].' - '.$fixture['description']
            );
        }
    }

    public static function provideExtensionShapes(): array
    {
        return [
            '5 of 2, extended to 6' => [5, 2, 6],
            '4 of 3, extended to 5' => [4, 3, 5],
            '8 of 2, extended to 9' => [8, 2, 9],
            '3 of 3, extended to 4' => [3, 3, 4],
        ];
    }

    /**
     * An added share is exactly the share that would have been issued at the time
     *
     * Recovering correctly only shows the new share is consistent with the ones it
     * was derived from. This pins something stronger: with the randomness fixed, so
     * that both runs build the same polynomial, issuing n shares and extending to x
     * produces the very bytes that issuing x shares outright would have produced.
     * Added shares are therefore indistinguishable from original ones, rather than
     * merely compatible with them.
     */
    #[DataProvider('provideExtensionShapes')]
    public function testAddedShareMatchesANativelyIssuedShare(int $count, int $threshold, int $extendTo): void
    {
        $secret = 'exactness check';

        Secret::setAlgorithm(new Shamir(), false);
        Secret::setRandomGenerator(new DeterministicGenerator(4242), false);
        $native = Secret::share($secret, $extendTo, $threshold);

        Secret::setAlgorithm(new Shamir(), false);
        Secret::setRandomGenerator(new DeterministicGenerator(4242), false);
        $partial = Secret::share($secret, $count, $threshold);

        // the generator must not influence extension at all
        Secret::setRandomGenerator(null, false);
        $added = Secret::addShares($partial, $extendTo - $count, $count);

        self::assertSame(
            array_slice($native, $count),
            $added,
            'Added shares differ from the shares the same polynomial would have issued.'
        );
    }

    public function testRejectsFewerKeysThanTheThreshold(): void
    {
        $shares = Secret::share('too few', 5, 3);

        $this->expectException(RuntimeException::class);
        Secret::addShares(array_slice($shares, 0, 2), 1, 5);
    }

    /**
     * Refuses to reissue a number that one of the given shares already uses
     */
    public function testRejectsASequenceThatWouldCollide(): void
    {
        $shares = Secret::share('collision', 5, 2);

        $this->expectException(OutOfRangeException::class);
        // share 5 was passed in, so 3 cannot be the highest ever issued
        Secret::addShares($shares, 1, 3);
    }

    /**
     * Understating the highest issued number duplicates rather than conflicts
     *
     * This is the failure the issue thread worried about, and it turns out to be the
     * benign kind. addShares() rebuilds the polynomial the secret was split with, so
     * evaluating it at a coordinate already in use reproduces that share byte for
     * byte instead of inventing a second value for it. Nothing decodes to a wrong
     * secret: pairing a duplicate with its twin is caught as a repeated share, and
     * pairing it with any other share still recovers correctly. What is lost is
     * distinctness - two holders unknowingly carry the same share.
     */
    public function testUnderstatedSequenceProducesADuplicateNotAConflict(): void
    {
        $secret = 'collision behaviour';
        $shares = Secret::share($secret, 10, 2);

        // caller holds only the first two and wrongly believes only two were issued
        $added = Secret::addShares([$shares[0], $shares[1]], 1, 2);

        self::assertSame($shares[2], $added[0], 'Re-issuing a used number did not reproduce the original share.');

        // the duplicate still recovers correctly alongside any other share
        self::assertSame($secret, Secret::recover([$shares[5], $added[0]]));

        // but pairing it with its twin is rejected rather than silently wrong
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Repeated share detected');
        Secret::recover([$shares[2], $added[0]]);
    }

    public function testRejectsNonPositiveAdditionalCount(): void
    {
        $shares = Secret::share('none', 3, 2);

        $this->expectException(OutOfRangeException::class);
        Secret::addShares($shares, 0, 3);
    }

    public function testRejectsExceedingThePrime(): void
    {
        $shares = Secret::share('too many', 3, 2);

        $this->expectException(OutOfRangeException::class);
        // a single byte chunk uses prime 257
        Secret::addShares($shares, 1, 256);
    }

    /**
     * The facade reports clearly when the algorithm in use cannot do this
     */
    public function testFacadeRejectsAnAlgorithmThatCannotExtend(): void
    {
        $shares = Secret::share('unsupported', 3, 2);

        Secret::setAlgorithm(new PlainAlgorithm(), false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot add shares');
        Secret::addShares($shares, 1, 3);
    }
}
