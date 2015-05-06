<?php

namespace TQ\Shamir\Tests;


use TQ\Shamir\Algorithm\Algorithm;
use TQ\Shamir\Algorithm\RandomGeneratorAware;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Secret;

class SecretTest extends \PHPUnit_Framework_TestCase
{

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
        $algorithm  = Secret::getAlgorithm();
        if (!$algorithm instanceof RandomGeneratorAware) {
            $this->markTestSkipped('Algorithm does not implement RandomGeneratorAware');
        }
        $this->assertSame($new, $algorithm->getRandomGenerator());
    }


}
