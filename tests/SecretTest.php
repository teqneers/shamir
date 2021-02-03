<?php

namespace TQ\Shamir\Tests;

use OutOfRangeException;
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
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';

    protected $secretAscii;

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
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
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
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $parameters);
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

    public function provideConvertBase()
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

    /**
     * @dataProvider provideConvertBase
     */
    public function testConvBase($numberInput, $fromBaseInput, $toBaseInput, $expected)
    {
        $returnVal = $this->invokeStaticMethod(
            Shamir::class,
            'convBase',
            [$numberInput, $fromBaseInput, $toBaseInput]
        );
        self::assertEquals($expected, $returnVal);
    }

    public function testReturnsDefaultAlgorithm()
    {
        self::assertInstanceOf(Algorithm::class, Secret::getAlgorithm());
    }

    public function testReturnsDefaultRandomGenerator()
    {
        self::assertInstanceOf(Generator::class, Secret::getRandomGenerator());
    }

    public function testSetNewAlgorithmReturnsOld()
    {
        $current = Secret::getAlgorithm();
        /** @var MockBuilder|Algorithm $new */
        $new = $this->getMockBuilder(Algorithm::class)->onlyMethods(['share', 'recover'])->getMock();

        self::assertSame($current, Secret::setAlgorithm($new));
        self::assertSame($new, Secret::getAlgorithm());

        // don't return old one with returnOld = false
        self::assertNull(Secret::setAlgorithm($new, false));
    }

    public function testSetNewRandomGeneratorReturnsOld()
    {
        $current = Secret::getRandomGenerator();
        /** @var MockBuilder|Generator $new */
        $new = $this->getMockBuilder(Generator::class)->onlyMethods(['getRandomInt'])->getMock();

        self::assertSame($current, Secret::setRandomGenerator($new));
        self::assertSame($new, Secret::getRandomGenerator());
    }

    public function testSetNewRandomGeneratorUpdatesGeneratorOnAlgorithm()
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

    public function provideShareAndRecoverMultipleBytes()
    {
        if (empty($this->secretAscii)) {
            // generate string with all ASCII chars
            $this->secretAscii = '';
            for ($i = 0; $i < 256; ++$i) {
                $this->secretAscii .= chr($i);
            }
        }

        $return = [];
        // add full ASCII charset
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = [$this->secretAscii, $bytes];
        }
        // add some unicode chars
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = [$this->secretUtf8, $bytes];
        }

        return $return;
    }

    /**
     * @dataProvider provideShareAndRecoverMultipleBytes
     */
    public function testShareAndRecoverMultipleBytes($secret, $bytes)
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

    public function testShareAndRecoverShuffleKeys()
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

    public function testShareAndRecoverOneByte()
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

    public function testShareAndRecoverTwoBytes()
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

    public function testShareAndRecoverThreeBytes()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 75000, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        self::assertSame($secret, $recover);
    }

    /**
     * @dataProvider provideShareAndRecoverMultipleBytes
     */
    public function testChunkSizeGetter($secret, $bytes)
    {
        $shamir = new Shamir();
        $shamir->setChunkSize($bytes);

        self::assertSame($shamir->getChunkSize(), $bytes);
    }

    public function provideChunkSize()
    {
        return [
            ['string'],
            [0],
            [8],
            [99],
            [0.5],
        ];
    }

    /**
     * @dataProvider provideChunkSize
     */
    public function testOpenSslGeneratorInputExceptions($chunkSize)
    {
        $this->expectException(OutOfRangeException::class);

        $shamir = new Shamir();
        $shamir->setChunkSize($chunkSize);
    }

    public function testShareAndShareSmallerThreshold()
    {
        $this->expectException(OutOfRangeException::class);

        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        Secret::share($secret, 1, 2);
    }
}
