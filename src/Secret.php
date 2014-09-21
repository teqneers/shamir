<?php

namespace TQ\Shamir;

/**
 * Class Secret
 *
 * Based on "Shamir's Secret Sharing class" from Kenny Millington
 *
 * @link    http://www.kennynet.co.uk/misc/shamir.class.txt
 *
 * @package TQ\Shamir
 */
class Secret
{
    /**
     * @var integer     Prime number has to be greater than 256
     */
    const prime = 257;

    /**
     * @var array Cache of the inverse table.
     */
    protected static $invTab;

    /**
     * Calculate module of any given number using prime
     *
     * @param   integer     Number
     * @return  integer     Module of number
     */
    protected static function modulo( $number )
    {
        $modulo = $number % self::prime;
        return ( $modulo < 0 ) ? $modulo + self::prime : $modulo;
    }

    /**
     * Calculates the a lookup table for reverse coefficients
     *
     * @return array
     */
    protected static function invTab()
    {
        if (!isset(self::$invTab)) {
            $x            = $y = 1;
            self::$invTab = array(0 => 0);
            for ($i = 0; $i < self::prime; $i++) {
                self::$invTab[$x] = $y;
                $x                = self::modulo(3 * $x);
                $y                = self::modulo(86 * $y);
            }
        }

        return self::$invTab;
    }

    /**
     * Calculates the inverse modulo
     *
     * @param int $i
     * @return int
     */
    protected static function inv($i)
    {
        $invTab = self::invTab();

        return ($i < 0) ? self::modulo(-$invTab[-$i]) : $invTab[$i];
    }

    /**
     * Calculates the reverse coefficients
     *
     * @param array $keyX
     * @param int   $quorum
     * @return array
     * @throws \RuntimeException
     */
    protected static function reverseCoefficients(array $keyX, $quorum)
    {
        $coefficients = array();

        for ($i = 0; $i < $quorum; $i++) {
            $temp = 1;
            for ($j = 0; $j < $quorum; $j++) {
                if ($i != $j) {
                    $temp = self::modulo(
                        -$temp * $keyX[$j] * self::inv($keyX[$i] - $keyX[$j])
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
     * @param   integer $threshold      Number of coefficients needed
     * @return  array                   Random coefficients
     */
    protected static function generateCoefficients( $threshold ) {
        $coefficients = array();
        for( $i = 0; $i < $threshold - 1; $i++ ) {
            $coefficients[] = self::modulo( mt_rand(0, PHP_INT_MAX) );
        }

        return $coefficients;
    }

    /**
     * Calculate y values of polynomial curve using horner's method
     *
     * @see     http://en.wikipedia.org/wiki/Horner%27s_method
     * @param   integer $x                  X coordinate
     * @param   array   $coefficients       Polynomial coefficients
     * @return  integer                     Y coordinate
     */
    protected static function hornerMethod( $x, array $coefficients ) {
        $y = 0;
        foreach( $coefficients as $c ) {
            $y = self::modulo( $x * $y + $c);
        }

        return $y;
    }

    /**
     * Generate shared secrets
     *
     * @param	string	$secret     Secret
     * @param	integer	$shares     Number of parts to share
     * @param	integer $threshold  Minimum number of shares required for decryption
     * @return  array               Secret shares
     * @throws \OutOfBoundsException
     */
    public static function share( $secret, $shares, $threshold = 2 ) {

        // check if number of shares is less than our prime, otherwise we have a security problem
        if( $shares >= self::prime || $shares < 1 ) {
            throw new \OutOfRangeException( 'Number of shares has to be between 0 and '.self::prime.'.' );
        }

        if( $shares < $threshold ) {
            throw new \OutOfRangeException( 'Threshold has to be between 0 and '.$threshold.'.' );
        }

        // divide secrete into single bytes, which we encrypt one by one
        $result = array();
        foreach( unpack('C*', $secret) as $byte ) {
            $coeffs = self::generateCoefficients( $threshold );
            $coeffs[] = $byte;

            // go through x coordinates and calculate y value
            for( $x = 1; $x <= $shares; $x++ ) {
                // use horner method to calculate y value
                $result[] = self::hornerMethod( $x, $coeffs );

            }
        }

        // convert y coordinates into hexadecimals shares
        $passwords = array();
        for ($i = 0; $i < $shares; $i++) {
            $key = sprintf("%02x%02x", $threshold, $i + 1);
            for ($j = 0; $j < strlen($secret); $j++) {
                $key .= ($result[$j * $shares + $i] == 256) ? "g0" : sprintf("%02x", $result[$j * $shares + $i]);
            }
            $passwords[] = substr($key, 0);
        }

        return $passwords;
    }

    /**
     * Recovers the secret from the given shared keys
     *
     * @param array $keys
     * @return string
     */
    public static function recover(array $keys)
    {
        $keyX   = array();
        $keyY   = array();
        $keyLen = 0;
        $quorum = 0;

        foreach ($keys as $key) {
            $quorum = intval(substr($key, 0, 2));
            $number = intval(substr($key, 2, 2));
            $key    = substr($key, 4);
            $keyLen = strlen($key);
            $keyX[] = $number;
            for ($i = 0; $i < strlen($key); $i += 2) {
                $keyY[] = (substr($key, $i, 2) == "g0") ? 256 : hexdec(substr($key, $i, 2));
            }
        }

        $keyLen /= 2;

        $coefficients = self::reverseCoefficients($keyX, $quorum);

        $secret = "";
        for ($i = 0; $i < $keyLen; $i++) {
            $temp = 0;
            for ($j = 0; $j < $quorum; $j++) {
                $temp = self::modulo(
                    $temp + $keyY[$keyLen * $j + $i] * $coefficients[$j]
                );
            }
            $secret .= chr($temp);
        }

        return $secret;
    }
}