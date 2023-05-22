<?php

namespace TQ\Shamir\Tests;

use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\RandomGeneratorAware;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Secret;

class SecretTest extends TestCase
{
    protected static $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';

    protected static $secretAscii;

    /**
     * Call protected/private method of a class.
     *
     * @param  object  $object      Instantiated object that we will run method on.
     * @param  string  $methodName  Method name to call
     * @param  array   $parameters  Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));

        return $reflection->getMethod($methodName)->invokeArgs($object, $parameters);
    }

    /**
     * Call protected/private static method of a class.
     *
     * @param  string  $class       Name of the class
     * @param  string  $methodName  Static method name to call
     * @param  array   $parameters  Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeStaticMethod($class, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($class);

        return $reflection->getMethod($methodName)->invokeArgs(null, $parameters);
    }

    protected function setUp(): void
    {
        Secret::setRandomGenerator(null);
        Secret::setAlgorithm(null);
    }

    protected function tearDown(): void
    {
        Secret::setRandomGenerator(null);
        Secret::setAlgorithm(null);
    }

    public static function provideConvertBase(): array
    {
        return [
            # dec -> dec
            [0, '0123456789', '0123456789', 0],
            [100, '0123456789', '0123456789', 100],
            [999, '0123456789', '0123456789', 999],

            # dec -> bin
            [0, '0123456789', '01', 0],
            [100, '0123456789', '01', "1100100"],
            [999, '0123456789', '01', "1111100111"],
            # dec -> oct
            [0, '0123456789', '01234567', 0],
            [100, '0123456789', '01234567', "144"],
            [999, '0123456789', '01234567', "1747"],
            # dec -> hex
            [0, '0123456789', '0123456789abcdef', 0],
            [100, '0123456789', '0123456789abcdef', "64"],
            [999, '0123456789', '0123456789abcdef', "3e7"],

            # bin -> dec
            [0, '01', '0123456789', 0],
            ["11111", '01', '0123456789', 31],
            ["101010101010", '01', '0123456789', 2730],
            # oct -> dec
            [0, '01234567', '0123456789', 0],
            ["100", '01234567', '0123456789', 64],
            ["77777", '01234567', '0123456789', 32767],
            # dec -> hex
            [0, '0123456789abcdef', '0123456789', 0],
            ['ffff', '0123456789abcdef', '0123456789', 65535],
            ['abcdef0123', '0123456789abcdef', '0123456789', 737894400291],
        ];
    }

    #[DataProvider('provideConvertBase')]
    public function testConvBase($numberInput, $fromBaseInput, $toBaseInput, $expected): void
    {
        $returnVal = $this->invokeStaticMethod(
            Shamir::class,
            'convBase',
            [$numberInput, $fromBaseInput, $toBaseInput]
        );
        self::assertEquals($expected, $returnVal);
    }

    public function testReturnsDefaultAlgorithm(): void
    {
        self::assertInstanceOf(Algorithm::class, Secret::getAlgorithm());
    }

    public function testReturnsDefaultRandomGenerator(): void
    {
        self::assertInstanceOf(Generator::class, Secret::getRandomGenerator());
    }

    public function testSetNewAlgorithmReturnsOld(): void
    {
        $current = Secret::getAlgorithm();
        /** @var MockBuilder|Algorithm $new */
        $new = $this->getMockBuilder(Algorithm::class)->onlyMethods(['share', 'recover'])->getMock();

        self::assertSame($current, Secret::setAlgorithm($new));
        self::assertSame($new, Secret::getAlgorithm());

        // don't return old one with returnOld = false
        self::assertNull(Secret::setAlgorithm($new, false));
    }

    public function testSetNewRandomGeneratorReturnsOld(): void
    {
        $current = Secret::getRandomGenerator();
        /** @var MockBuilder|Generator $new */
        $new = $this->getMockBuilder(Generator::class)->onlyMethods(['getRandomInt'])->getMock();

        self::assertSame($current, Secret::setRandomGenerator($new));
        self::assertSame($new, Secret::getRandomGenerator());
    }

    public function testSetNewRandomGeneratorUpdatesGeneratorOnAlgorithm(): void
    {
        /** @var MockBuilder|Generator $new */
        $new = $this->getMockBuilder(Generator::class)->onlyMethods(['getRandomInt'])->getMock();

        Secret::setRandomGenerator($new);
        $algorithm = Secret::getAlgorithm();
        if (!$algorithm instanceof RandomGeneratorAware) {
            self::markTestSkipped('Algorithm does not implement RandomGeneratorAware');
        }
        self::assertSame($new, $algorithm->getRandomGenerator());
    }

    public static function provideShareAndRecoverMultipleBytes(): array
    {
        if (empty(self::$secretAscii)) {
            // generate string with all ASCII chars
            self::$secretAscii = '';
            for ($i = 0; $i < 256; ++$i) {
                self::$secretAscii .= chr($i);
            }
        }

        $return = [];
        // add full ASCII charset
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = [self::$secretAscii, $bytes];
        }
        // add some unicode chars
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = [self::$secretUtf8, $bytes];
        }

        return $return;
    }

    #[DataProvider('provideShareAndRecoverMultipleBytes')]
    public function testShareAndRecoverMultipleBytes($secret, $bytes): void
    {
        $shamir = new Shamir();
        $shamir->setChunkSize($bytes);

        $shares = $shamir->share($secret, 2, 2);

        // create new instance to check if all necessary values
        // are set with the keys
        $shamir  = new Shamir();
        $recover = $shamir->recover(array_slice($shares, 0, 2));
        self::assertSame($secret, $recover);
    }

    public function testShareAndRecoverShuffleKeys(): void
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 50, 2);

        $num = count($shares);
        for ($i = 0; $i < $num; ++$i) {
            for ($j = $i + 1; $j < $num; ++$j) {
                $recover = Secret::recover([$shares[$i], $shares[$j]]);
                self::assertSame($secret, $recover);
            }
        }
    }

    public function testShareAndRecoverOneByte(): void
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 10, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        self::assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 2, 2));
        self::assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 4, 2));
        self::assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 5, 4));
        self::assertSame($secret, $recover);

        // test different length of secret
        $template = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 1; $i <= 8; ++$i) {
            $secret = substr($template, 0, $i);
            $shares = Secret::share($secret, 3, 2);

            $recover = Secret::recover(array_slice($shares, 0, 2));
            self::assertSame($secret, $recover);
        }
    }

    public function testShareAndRecoverTwoBytes(): void
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 260, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        self::assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 2, 2));
        self::assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 4, 2));
        self::assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 6, 4));
        self::assertSame($secret, $recover);

        // test different length of secret
        $template = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 1; $i <= 8; ++$i) {
            $secret = substr($template, 0, $i);
            $shares = Secret::share($secret, 260, 2);

            $recover = Secret::recover(array_slice($shares, 0, 2));
            self::assertSame($secret, $recover);
        }
    }

    public function testShareAndRecoverThreeBytes(): void
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 75000, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        self::assertSame($secret, $recover);
    }

    #[DataProvider('provideShareAndRecoverMultipleBytes')]
    public function testChunkSizeGetter($secret, $bytes): void
    {
        $shamir = new Shamir();
        $shamir->setChunkSize($bytes);

        self::assertSame($shamir->getChunkSize(), $bytes);
    }

    public static function provideChunkSize(): array
    {
        return [
            [0],
            [8],
            [99],
            [0.5],
        ];
    }

    #[DataProvider('provideChunkSize')]
    public function testOpenSslGeneratorInputExceptions($chunkSize): void
    {
        $this->expectException(OutOfRangeException::class);

        $shamir = new Shamir();
        $shamir->setChunkSize($chunkSize);
    }

    public function testShareAndShareSmallerThreshold(): void
    {
        $this->expectException(OutOfRangeException::class);

        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        Secret::share($secret, 1, 2);
    }
}
