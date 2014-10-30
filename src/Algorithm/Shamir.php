<?php

namespace TQ\Shamir\Algorithm;


use TQ\Shamir\Random\Generator;
use TQ\Shamir\Random\PhpGenerator;

/**
 * Class Shamir
 *
 * Based on "Shamir's Secret Sharing class" from Kenny Millington
 *
 * @link    http://www.kennynet.co.uk/misc/shamir.class.txt
 *
 * @package TQ\Shamir\Algorithm
 */
class Shamir implements Algorithm, RandomGeneratorAware
{
    /**
     * Calculation base (decimal)
     *
     * @const string
     */
    const DECIMAL = '0123456789';

    /**
     * Target base characters to be used in passwords (shares)
     *
     * @const string
     */
    const CHARS = '0123456789abcdefghijklmnopqrstuvwxyz';

    /**
     * Prime number has to be greater than 256
     *
     * @var int
     */
    protected $prime = 4294967311;

    /**
     * Part size in bytes
     *
     * The secret will be divided in different by sizes.
     * This value defines how many bytes will get encoded
     * at once.
     *
     * @var int
     */
    protected $partSize = 1;

    /**
     * The random generator
     *
     * @var Generator
     */
    protected $randomGenerator;

    /**
     * Cache of the inverse table
     *
     * @var array
     */
    protected $invTab;


    /**
     * @inheritdoc
     */
    public function setRandomGenerator(Generator $generator)
    {
        $this->randomGenerator = $generator;
    }

    /**
     * @inheritdoc
     */
    public function getRandomGenerator()
    {
        if (!$this->randomGenerator) {
            $this->randomGenerator = new PhpGenerator();
        }

        return $this->randomGenerator;
    }

    /**
     * Calculate modulo of any given number using prime
     *
     * @param   integer     Number
     * @return  integer     Module of number
     */
    protected function modulo($number)
    {
        $modulo = $number % $this->prime;

        return ($modulo < 0) ? $modulo + $this->prime : $modulo;
    }

    /**
     * Calculates the a lookup table for reverse coefficients
     *
     * @return array
     */
    protected function invTab()
    {
        if (!isset($this->invTab)) {
            $x = $y = 1;
            $this->invTab = array(0 => 0);
            for ($i = 0; $i < $this->prime; $i++) {
                $this->invTab[$x] = $y;
                $x = $this->modulo(3 * $x);
                $y = $this->modulo(86 * $y);
            }
        }
        print_r($this->invTab);

        return $this->invTab;
    }

    /**
     * Calculates the inverse modulo
     *
     * @param int $i
     * @return int
     */
    protected function inv($i)
    {
        $invTab = $this->invTab();

        return ($i < 0) ? $this->modulo(-$invTab[-$i]) : $invTab[$i];
    }

    /**
     * Calculates the reverse coefficients
     *
     * @param   array $keyX
     * @param   int $threshold
     * @return  array
     * @throws  \RuntimeException
     */
    protected function reverseCoefficients(array $keyX, $threshold)
    {
        $coefficients = array();

        for ($i = 0; $i < $threshold; $i++) {
            $temp = 1;
            for ($j = 0; $j < $threshold; $j++) {
                if ($i != $j) {
                    $temp = $this->modulo(
                        -$temp * $keyX[$j] * $this->inv($keyX[$i] - $keyX[$j])
                    );
                }
            }

            if ($temp == 0) {
                /* Repeated share. */
                throw new \RuntimeException('Repeated share detected - cannot compute reverse-coefficients');
            }

            $coefficients[] = $temp;
        }

        return $coefficients;
    }

    /**
     * Generate random coefficient
     *
     * @param   integer $threshold Number of coefficients needed
     * @return  array                   Random coefficients
     */
    protected function generateCoefficients($threshold)
    {
        $coefficients = array();
        for ($i = 0; $i < $threshold - 1; $i++) {
            do {
                // the random number has to be positive integer != 0
                $random = abs($this->getRandomGenerator()->getRandomInt());
            } while ($random < 1);
            $coefficients[] = $this->modulo($random);
        }

        return $coefficients;
    }

    /**
     * Calculate y values of polynomial curve using horner's method
     *
     * Horner converts a polynomial formula like
     * 11 + 7x - 5x^2 - 4x^3 + 2x^4
     * into a more efficient formula
     * 11 + x * ( 7 + x * ( -5 + x * ( -4 + x * 2 ) ) )
     *
     * @see     http://en.wikipedia.org/wiki/Horner%27s_method
     * @param   integer $x X coordinate
     * @param   array $coefficients Polynomial coefficients
     * @return  integer                     Y coordinate
     */
    protected function hornerMethod($x, array $coefficients)
    {
        $y = 0;
        foreach ($coefficients as $c) {
            $y = $this->modulo($x * $y + $c);
        }

        return $y;
    }

    /**
     * Converts from $fromBaseInput to $toBaseInput
     *
     * @param   string $numberInput
     * @param   string $fromBaseInput
     * @param   string $toBaseInput
     * @return  string
     */
    protected static function convBase($numberInput, $fromBaseInput, $toBaseInput)
    {
        if ($fromBaseInput == $toBaseInput) {
            return $numberInput;
        }
        $fromBase = str_split($fromBaseInput, 1);
        $toBase = str_split($toBaseInput, 1);
        $number = str_split($numberInput, 1);
        $fromLen = strlen($fromBaseInput);
        $toLen = strlen($toBaseInput);
        $numberLen = strlen($numberInput);
        $retVal = '';
        if ($toBaseInput == '0123456789') {
            $retVal = 0;
            for ($i = 1; $i <= $numberLen; $i++) {
                $retVal = bcadd(
                    $retVal,
                    bcmul(array_search($number[$i - 1], $fromBase), bcpow($fromLen, $numberLen - $i))
                );
            }

            return $retVal;
        }
        if ($fromBaseInput != '0123456789') {
            $base10 = self::convBase($numberInput, $fromBaseInput, '0123456789');
        } else {
            $base10 = $numberInput;
        }
        if ($base10 < strlen($toBaseInput)) {
            return $toBase[$base10];
        }
        while ($base10 != '0') {
            $retVal = $toBase[bcmod($base10, $toLen)] . $retVal;
            $base10 = bcdiv($base10, $toLen, 0);
        }

        return $retVal;
    }


    /**
     * Configure encoding parameters
     *
     * Depending on the number of required keys, we need to change
     * prime number, key length and more
     *
     * @param   int $max Maximum number of keys needed
     * @throws  \OutOfRangeException
     */
    protected function setMaxShares($max)
    {

        $maxPosible = pow(2, PHP_INT_SIZE * 8 - 1);
        if ($max >= $maxPosible) {
            // we are unable to provide more bytes-1 as supported by OS
            // because the prime number need to be higher than that, but
            // this would exceed OS int range.
            throw new \OutOfRangeException(
                'Number of required keys has to be below ' . number_format(pow(2, PHP_INT_SIZE * 8 - 1)) . '.'
            );
        }

        // calculate how many bytes we need to represent this number
        $bytes = ceil(log($max, 2) / 8);
        switch ($bytes) {
            case 1:
                // 256
                $prime = 257;
                break;
            case 2:
                // 2 bytes: 65536
                $prime = 65537;
                break;
            case 3:
                // 3 bytes: 16777216
                $prime = 1677727;
                break;
            case 4:
                // 4 bytes: 4294967296
                $prime = 4294967311;
                break;
            case 5:
                // 5 bytes: 1099511627776
                $prime = 1099511627791;
                break;
            case 6:
                // 6 bytes: 281474976710656
                $prime = 281474976710677;
                break;
            case 7:
                // 7 bytes: 72057594037927936
                $prime = 72057594037928017;
                break;
        }

        $this->partSize = $bytes;
        $this->prime = $prime;
    }


    /**
     * Unpack a binary string and convert it into decimals
     *
     * Convert each chunk of a binary data into decimal numbers.
     *
     * @param   string $string Binary string
     * @return  array           Array with decimal converted numbers
     */
    protected function unpack($string)
    {
        $part = 0;
        $int = null;
        $return = array();
        foreach (unpack('C*', $string) as $byte) {
            $int += $byte * pow(2, $part * 8);
            if (++$part == $this->partSize) {
                $return[] = $int;
                $part = 0;
                $int = null;
            }
        }
        if ($part != 0) {
            $return[] = $int;
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function share($secret, $shares, $threshold = 2)
    {
        $this->setMaxShares($shares);

        $a = self::convBase(123456789, self::DECIMAL, self::CHARS);
        $b = self::convBase($a, self::CHARS, self::DECIMAL);
        $c = self::convBase(str_repeat(substr(self::CHARS, -1), 4), self::CHARS, self::DECIMAL);
//        var_dump($a);
//        var_dump($b);
//        var_dump($c);

        // check if number of shares is less than our prime, otherwise we have a security problem
        if ($shares >= $this->prime || $shares < 1) {
            throw new \OutOfRangeException('Number of shares has to be between 0 and ' . $this->prime . '.');
        }

        if ($shares < $threshold) {
            throw new \OutOfRangeException('Threshold has to be between 0 and ' . $threshold . '.');
        }

        // divide secret into single bytes, which we encrypt one by one
        $result = array();
        foreach ($this->unpack($secret) as $bytes) {
            $coeffs = $this->generateCoefficients($threshold);
            $coeffs[] = $bytes;

            // go through x coordinates and calculate y value
            for ($x = 1; $x <= $shares; $x++) {
                // use horner method to calculate y value
                $result[] = $this->hornerMethod($x, $coeffs);
            }
        }


        // convert y coordinates into hexadecimals shares
        $passwords = array();
        for ($i = 0; $i < $shares; $i++) {
            $key = sprintf("%02x%02x", $threshold, $i + 1);
            for ($j = 0; $j < ceil(strlen($secret)/$this->partSize); $j++) {
                $key .= str_pad(
                    self::convBase($result[$j * $shares + $i], self::DECIMAL, self::CHARS),
                    $this->partSize+1,
                    0,
                    STR_PAD_LEFT
                );

            }
            $passwords[] = substr($key, 0);
        }

        return $passwords;
    }

    /**
     * @inheritdoc
     */
    public function recover(array $keys)
    {
        if (!count($keys)) {
            throw new \RuntimeException('No keys given.');
        }

        $keyX = array();
        $keyY = array();
        $keyLen = 0;
        $threshold = 0;

        foreach ($keys as $key) {
            if ($threshold === 0) {
                $threshold = (int)substr($key, 0, 2);
            } elseif ($threshold != (int)substr($key, 0, 2)) {
                throw new \RuntimeException('Given keys are incompatible.');
            } elseif ($threshold < count($keys)) {
                throw new \RuntimeException('Not enough keys to disclose secret.');
            }
            $keyX[] = (int)substr($key, 2, 2);
            $key = substr($key, 4);
            if ($keyLen === 0) {
                $keyLen = strlen($key);
            } elseif ($keyLen != strlen($key)) {
                throw new \RuntimeException('Given keys vary in key length.');
            }
            for ($i = 0; $i < strlen($key); $i += 2) {
                $keyY[] = self::convBase(substr($key, $i, 2), self::CHARS, self::DECIMAL);
            }
        }

        $keyLen /= 2;

        $coefficients = $this->reverseCoefficients($keyX, $threshold);

        $secret = "";
        for ($i = 0; $i < $keyLen; $i++) {
            $temp = 0;
            for ($j = 0; $j < $threshold; $j++) {
                $temp = $this->modulo(
                    $temp + $keyY[$keyLen * $j + $i] * $coefficients[$j]
                );
            }
            $secret .= chr($temp);
        }

        return $secret;
    }


}