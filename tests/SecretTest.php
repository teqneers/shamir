<?php

namespace TQ\Shamir\Tests;


use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\RandomGeneratorAware;
use TQ\Shamir\Algorithm\Shamir;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Secret;

class SecretTest extends \PHPUnit_Framework_TestCase
{
    protected $secretUtf8 = 'Lorem ipsum dolor sit असरकारक संस्थान δισεντιας قبضتهم нолюёжжэ 問ナマ業71職げら覧品モス変害';
    protected $secretAscii;

    protected function setUp()
    {
        Secret::setRandomGenerator(null);
        Secret::setAlgorithm(null);
    }

    protected function tearDown()
    {
        Secret::setRandomGenerator(null);
        Secret::setAlgorithm(null);
    }

    public function testReturnsDefaultAlgorithm()
    {
        $this->assertInstanceOf('\TQ\Shamir\Algorithm\Algorithm', Secret::getAlgorithm());
    }

    public function testReturnsDefaultRandomGenerator()
    {
        $this->assertInstanceOf('\TQ\Shamir\Random\Generator', Secret::getRandomGenerator());
    }

    public function testSetNewAlgorithmReturnsOld()
    {
        $current = Secret::getAlgorithm();
        /** @var \PHPUnit_Framework_MockObject_MockObject|Algorithm $new */
        $new = $this->getMockBuilder('\TQ\Shamir\Algorithm\Algorithm')->setMethods(['share', 'recover'])->getMock();

        $this->assertSame($current, Secret::setAlgorithm($new));
        $this->assertSame($new, Secret::getAlgorithm());
    }

    public function testSetNewRandomGeneratorReturnsOld()
    {
        $current = Secret::getRandomGenerator();
        /** @var \PHPUnit_Framework_MockObject_MockObject|Generator $new */
        $new = $this->getMockBuilder('\TQ\Shamir\Random\Generator')->setMethods(['getRandomInt'])->getMock();

        $this->assertSame($current, Secret::setRandomGenerator($new));
        $this->assertSame($new, Secret::getRandomGenerator());
    }

    public function testSetNewRandomGeneratorUpdatesGeneratorOnAlgorithm()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Generator $new */
        $new = $this->getMockBuilder('\TQ\Shamir\Random\Generator')->setMethods(['getRandomInt'])->getMock();

        Secret::setRandomGenerator($new);
        $algorithm = Secret::getAlgorithm();
        if (!$algorithm instanceof RandomGeneratorAware) {
            $this->markTestSkipped('Algorithm does not implement RandomGeneratorAware');
        }
        $this->assertSame($new, $algorithm->getRandomGenerator());
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

        $return = array();
		// add full ASCII charset
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = array($this->secretAscii, $bytes);
        }
		// add some unicode chars
        for ($bytes = 1; $bytes < 8; ++$bytes) {
            $return[] = array($this->secretUtf8, $bytes);
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
        $shamir = new Shamir();
        $recover = $shamir->recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);
    }

    public function testShareAndRecoverShuffleKeys()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 50, 2);

		for ($i = 0; $i < count($shares); ++$i) {
			for ($j = $i+1; $j < count($shares); ++$j) {
	        	$recover = Secret::recover(array($shares[$i], $shares[$j]));
	        	$this->assertSame($secret, $recover);
			}
		}
    }

    public function testShareAndRecoverOneByte()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 10, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 2, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 4, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 5, 4));
        $this->assertSame($secret, $recover);

        // test different length of secret
        $template = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 1; $i <= 8; ++$i) {
            $secret = substr($template, 0, $i);
            $shares = Secret::share($secret, 3, 2);

            $recover = Secret::recover(array_slice($shares, 0, 2));
            $this->assertSame($secret, $recover);
        }
    }

    public function testShareAndRecoverTwoBytes()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 260, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 2, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 4, 2));
        $this->assertSame($secret, $recover);

        $recover = Secret::recover(array_slice($shares, 6, 4));
        $this->assertSame($secret, $recover);

        // test different length of secret
        $template = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 1; $i <= 8; ++$i) {
            $secret = substr($template, 0, $i);
            $shares = Secret::share($secret, 260, 2);

            $recover = Secret::recover(array_slice($shares, 0, 2));
            $this->assertSame($secret, $recover);
        }

    }

    public function testShareAndRecoverThreeBytes()
    {
        $secret = 'abc ABC 123 !@# ,./ \'"\\ <>?';

        $shares = Secret::share($secret, 75000, 2);

        $recover = Secret::recover(array_slice($shares, 0, 2));
        $this->assertSame($secret, $recover);
    }


}
