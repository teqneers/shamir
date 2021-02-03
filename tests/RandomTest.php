<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\TestCase;
use TQ\Shamir\Random\OpenSslGenerator;
use TQ\Shamir\Random\PhpGenerator;

class RandomTest extends TestCase
{
    protected static $num = 100;

    protected static $byte = 3;    // 3 bytes negative

    protected static $min;

    protected static $max;

    public static function setUpBeforeClass(): void
    {
        self::$min = 1;
        self::$max = 1 << (self::$byte * 8);
    }

    protected function checkRandom($list)
    {
        // this test is NOT used to test quality of randomization in general,
        // but rather, if the randomizers have been initialized correctly
        // and deliver different results

        // max 10% of equal numbers
        $repetition = self::$num * 0.1;
        foreach ($list as $r => $n) {
            self::assertLessThan(
                $repetition,
                $n,
                'Not random enough. Too many equal numbers found. In theory this might happen, but is very unlikely. You might want to run test again.'
            );
            self::assertLessThanOrEqual(self::$max, $r, 'Random number bigger than expected.');
            self::assertGreaterThanOrEqual(self::$min, $r, 'Random number bigger than expected.');
        }

        // check if we get at least 75% unique/different numbers
        $distribution = self::$num * 0.75;
        self::assertGreaterThan(
            $distribution,
            count($list),
            'Not random. Too many same results. In theory this might happen. You might want wo run test again.'
        );
    }

    public function provideRandomInput()
    {
        return [
            [PHP_INT_MAX, 'string', 'OutOfRangeException'],
            [PHP_INT_MAX, 0, 'OutOfRangeException'],
            [PHP_INT_MAX, 0.5, 'OutOfRangeException'],
            [1, 5, 'ValueError'],
        ];
    }

    /**
     * @dataProvider provideRandomInput
     */
    public function testPhpGeneratorInputException($max, $min, $exception)
    {
        $this->expectException($exception);
        $generator = new PhpGenerator($max, $min);
        $generator->getRandomInt();
    }

    public function testPhpGeneratorSequence()
    {
        $random = new PhpGenerator(self::$max, self::$min);
        $i      = self::$num;
        $list   = [];
        while ($i--) {
            $r = $random->getRandomInt();
            if (!isset($list[$r])) {
                $list[$r] = 0;
            }
            ++$list[$r];
        }

        $this->checkRandom($list);
    }

    public function testPhpGeneratorInit()
    {
        $i    = self::$num;
        $list = [];
        while ($i--) {
            $random = new PhpGenerator(self::$max, self::$min);
            $r      = $random->getRandomInt();
            if (!isset($list[$r])) {
                $list[$r] = 0;
            }
            ++$list[$r];
        }

        $this->checkRandom($list);
    }

    public function testOpenSslGeneratorInput()
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            self::markTestSkipped('OpenSSL not compiled into PHP.');
        }

        $random = new OpenSslGenerator(self::$byte);

        // check default is forceStrong
        self::assertTrue($random->isForceStrong());
    }

    public function provideOpenSslBytes()
    {
        return [
            ['string'],
            [0],
            [0.5],
        ];
    }

    /**
     * @dataProvider provideOpenSslBytes
     */
    public function testOpenSslGeneratorInputException($bytes)
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            self::markTestSkipped('OpenSSL not compiled into PHP.');
        }

        $this->expectException('OutOfRangeException');
        new OpenSslGenerator($bytes);
    }

    public function testOpenSslGeneratorSequence()
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            self::markTestSkipped('OpenSSL not compiled into PHP.');
        }

        $random = new OpenSslGenerator(self::$byte);
        $i      = self::$num;
        $list   = [];
        while ($i--) {
            $r = $random->getRandomInt();
            if (!isset($list[$r])) {
                $list[$r] = 0;
            }
            ++$list[$r];
        }

        $this->checkRandom($list);
    }

    public function testOpenSslGeneratorInit()
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            self::markTestSkipped('OpenSSL not compiled into PHP.');
        }

        $i    = self::$num;
        $list = [];
        while ($i--) {
            $random = new OpenSslGenerator(self::$byte);
            $r      = $random->getRandomInt();
            if (!isset($list[$r])) {
                $list[$r] = 0;
            }
            ++$list[$r];
        }

        $this->checkRandom($list);
    }
}
