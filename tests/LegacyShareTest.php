<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Secret;

/**
 * Guards the on-disk share format against accidental change
 *
 * Shares are long-lived: someone may hold a share for a decade before needing to
 * restore it, quite possibly on a server old enough to be stuck on an earlier
 * release of this library. That only works while the encoding stays fixed, and
 * nothing in the unit tests would notice if it did not - they create and recover
 * shares in the same process, so a changed alphabet, padding character, chunk
 * size or prime would round-trip happily and still orphan every share ever issued.
 *
 * The fixtures are therefore real output captured from released versions running
 * on the PHP they targeted (1.1.0 on PHP 7.4, 2.0.1 on PHP 8.1), recovered here
 * by the current code. If this test fails, the format has changed and previously
 * issued shares can no longer be restored - which is a released-version problem,
 * not a test problem.
 *
 * @see tests/fixtures/legacy-shares.json
 */
class LegacyShareTest extends TestCase
{
    protected const FIXTURE = __DIR__.'/fixtures/legacy-shares.json';

    protected const KNOWN_ANSWER_FIXTURE = __DIR__.'/fixtures/known-answer-shares.json';

    protected function setUp(): void
    {
        // Secret's algorithm and generator are process-wide statics that other test
        // classes also write to. Starting from a known instance keeps these vectors
        // independent of test order. Shamir::setMaxShares() no longer lets a chunk
        // size leak between calls, so this is isolation hygiene rather than a
        // workaround - SecretTest::testShareIsNotAffectedByEarlierCalls covers that.
        Secret::setAlgorithm(new Shamir(), false);
    }

    protected function tearDown(): void
    {
        // both are static state shared with the rest of the suite
        Secret::setRandomGenerator(null, false);
        Secret::setAlgorithm(new Shamir(), false);
    }

    public static function provideLegacyShares(): array
    {
        $fixtures = json_decode(file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);

        $cases = [];
        foreach ($fixtures as $fixture) {
            $name = $fixture['createdBy'].' - '.$fixture['description'];

            $cases[$name] = [
                $fixture['secret'],
                $fixture['threshold'],
                $fixture['shares'],
            ];
        }

        return $cases;
    }

    /**
     * Every sufficient combination of stored shares has to yield the original secret
     */
    #[DataProvider('provideLegacyShares')]
    public function testRecoversSharesCreatedByOlderVersions(string $secret, int $threshold, array $shares): void
    {
        self::assertGreaterThanOrEqual(
            $threshold,
            count($shares),
            'Fixture stores fewer shares than its own threshold requires.'
        );

        foreach (self::selectCombinations($shares, $threshold) as $label => $combination) {
            self::assertSame(
                $secret,
                Secret::recover($combination),
                'Shares created by an earlier release no longer recover the secret ('.$label.').'
            );
        }
    }

    /**
     * Fewer shares than the threshold must not disclose the secret
     */
    #[DataProvider('provideLegacyShares')]
    public function testBelowThresholdDoesNotRecoverSecret(string $secret, int $threshold, array $shares): void
    {
        if ($threshold < 2) {
            self::markTestSkipped('A threshold of one cannot be undercut.');
        }

        $tooFew = array_slice($shares, 0, $threshold - 1);

        try {
            $recovered = Secret::recover($tooFew);
        } catch (\Throwable $e) {
            // refusing outright is the expected outcome
            self::assertNotInstanceOf(\Error::class, $e, 'Expected a domain exception, not an engine error.');

            return;
        }

        self::assertNotSame($secret, $recovered, 'Secret recovered from fewer shares than the threshold.');
    }

    public static function provideKnownAnswers(): array
    {
        $fixtures = json_decode(file_get_contents(self::KNOWN_ANSWER_FIXTURE), true, 512, JSON_THROW_ON_ERROR);

        $cases = [];
        foreach ($fixtures as $fixture) {
            $cases[$fixture['createdBy'].' - '.$fixture['description']] = [
                $fixture['secret'],
                $fixture['seed'],
                $fixture['shareCount'],
                $fixture['threshold'],
                $fixture['shares'],
            ];
        }

        return $cases;
    }

    /**
     * Sharing has to still produce the exact bytes an earlier release produced
     *
     * Recovering old shares proves the decoder is unchanged, but not the encoder:
     * anything that only affects newly created shares - the default prime, say -
     * slips through, because recovery resets those values from the share header.
     * Pinning the encoder's output closes that gap, which needs the randomness to
     * be reproducible, hence the injected generator.
     */
    #[DataProvider('provideKnownAnswers')]
    public function testShareStillProducesTheSameBytesAsOlderVersions(
        string $secret,
        int $seed,
        int $shareCount,
        int $threshold,
        array $expected
    ): void {
        Secret::setRandomGenerator(new DeterministicGenerator($seed), false);

        self::assertSame(
            $expected,
            Secret::share($secret, $shareCount, $threshold),
            'The encoder no longer produces the share format that earlier releases did.'
        );
    }

    /**
     * Picks a deterministic handful of share combinations
     *
     * Testing every combination would mean 260-choose-2 for the largest fixture,
     * so take the first, the last and an evenly spread selection instead.
     */
    protected static function selectCombinations(array $shares, int $threshold): array
    {
        $count = count($shares);

        $combinations = [
            'first '.$threshold => array_slice($shares, 0, $threshold),
            'last '.$threshold  => array_slice($shares, -$threshold),
        ];

        if ($count > $threshold) {
            $spread = [];
            for ($i = 0; $i < $threshold; $i++) {
                $spread[] = $shares[(int)floor($i * ($count - 1) / max(1, $threshold - 1))];
            }
            $combinations['spread'] = $spread;
        }

        return $combinations;
    }
}
