<?php

namespace TQ\Shamir\Tests;

use PHPUnit\Framework\TestCase;
use TQ\Shamir\Random\OpenSslGenerator;
use TQ\Shamir\Random\PhpGenerator;

class RandomTest extends TestCase
{
    protected $num = 100;

    protected $byte = 3;    // 3 bytes negative

    protected $min;

    protected $max;

    public function __construct()
    {
        parent::__construct();

        $this->min = -1 << ($this->byte * 8);
        $this->max = 1 << ($this->byte * 8);
    }

    protected function checkRandom($list)
    {
        // this test is NOT used to test quality of randomization in general,
        // but rather, if the randomizers have been initialized correctly
        // and deliver different results

        // max 10% of equal numbers
        $repetition = $this->num * 0.1;
        foreach ($list as $r => $n) {
            self::assertLessThan(
                $repetition,
                $n,
                'Not random enough. Too many equal numbers found. In theory this might happen, but is very unlikely. You might want to run test again.'
            );
            self::assertLessThanOrEqual($this->max, $r, 'Random number bigger than expected.');
            self::assertGreaterThanOrEqual($this->min, $r, 'Random number bigger than expected.');
        }

        // check if we get at least 75% unique/different numbers
        $distribution = $this->num * 0.75;
        self::assertGreaterThan(
            $distribution,
            count($list),
            'Not random. Too many same results. In theory this might happen. You might want wo run test again.'
        );
    }

    public function testPhpGeneratorSequence()
    {
        $random = new PhpGenerator($this->max, $this->min);
        $i      = $this->num;
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
        $i    = $this->num;
        $list = [];
        while ($i--) {
            $random = new PhpGenerator($this->max, $this->min);
            $r      = $random->getRandomInt();
            if (!isset($list[$r])) {
                $list[$r] = 0;
            }
            ++$list[$r];
        }

        $this->checkRandom($list);
    }

    public function testOpenSslGeneratorSequence()
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            self::markTestSkipped('OpenSSL not compiled into PHP.');
        }

        $random = new OpenSslGenerator($this->byte);
        $i      = $this->num;
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

        $i    = $this->num;
        $list = [];
        while ($i--) {
            $random = new OpenSslGenerator($this->byte);
            $r      = $random->getRandomInt();
            if (!isset($list[$r])) {
                $list[$r] = 0;
            }
            ++$list[$r];
        }

        $this->checkRandom($list);
    }
}
