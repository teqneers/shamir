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
//    const prime = 257;
    const prime = 4294967311;

    /**
     * @var string      Calculation base (decimal)
     */
    const decimal = '0123456789';

    /**
     * @var string      Target base characters to be used in passwords (shares)
     */
//    const keyChars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@#';
    const keyChars = '0123456789abcdefg';

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
print_r(self::$invTab);
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
     * @param int   $threshold
     * @return array
     * @throws \RuntimeException
     */
    protected static function reverseCoefficients(array $keyX, $threshold)
    {
        $coefficients = array();

        for ($i = 0; $i < $threshold; $i++) {
            $temp = 1;
            for ($j = 0; $j < $threshold; $j++) {
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
     * Horner converts a polynomial formula like
     * 11 + 7x - 5x^2 - 4x^3 + 2x^4
     * into a more efficient formula
     * 11 + x * ( 7 + x * ( -5 + x * ( -4 + x * 2 ) ) )
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


    protected static function convBase( $numberInput, $fromBaseInput, $toBaseInput )
    {
        if ($fromBaseInput==$toBaseInput) return $numberInput;
        $fromBase = str_split($fromBaseInput,1);
        $toBase = str_split($toBaseInput,1);
        $number = str_split($numberInput,1);
        $fromLen=strlen($fromBaseInput);
        $toLen=strlen($toBaseInput);
        $numberLen=strlen($numberInput);
        $retval='';
        if ($toBaseInput == '0123456789')
        {
            $retval=0;
            for ($i = 1;$i <= $numberLen; $i++)
                $retval = bcadd($retval, bcmul(array_search($number[$i-1], $fromBase),bcpow($fromLen,$numberLen-$i)));
            return $retval;
        }
        if ($fromBaseInput != '0123456789')
            $base10=self::convBase($numberInput, $fromBaseInput, '0123456789');
        else
            $base10 = $numberInput;
        if ($base10<strlen($toBaseInput))
            return $toBase[$base10];
        while($base10 != '0')
        {
            $retval = $toBase[bcmod($base10,$toLen)].$retval;
            $base10 = bcdiv($base10,$toLen,0);
        }
        return $retval;
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

        var_dump(self::prime/pow(2,32));
        exit;

        $a = self::convBase(123456789, self::decimal, self::keyChars);
        $b = self::convBase($a, self::keyChars, self::decimal);
        $c = self::convBase(str_repeat(substr(self::keyChars, -1), 4), self::keyChars, self::decimal);
        var_dump($a);
        var_dump($b);
        var_dump($c);

        // check if number of shares is less than our prime, otherwise we have a security problem
        if( $shares >= self::prime || $shares < 1 ) {
            throw new \OutOfRangeException( 'Number of shares has to be between 0 and '.self::prime.'.' );
        }

        if( $shares < $threshold ) {
            throw new \OutOfRangeException( 'Threshold has to be between 0 and '.$threshold.'.' );
        }

        // divide secret into single bytes, which we encrypt one by one
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
                $key .= str_pad( self::convBase($result[$j * $shares + $i], self::decimal, self::keyChars), 2, 0, STR_PAD_LEFT ) ;

            }
            $passwords[] = substr($key, 0);
        }

        return $passwords;
    }


    public static function disclose( array $shares ) {

    }


    /**
     * Recovers the secret from the given shared keys
     *
     * @param array $keys
     * @return string
     */
    public static function recover(array $keys)
    {
        if( !count($keys) ) {
            throw new \RuntimeException('No keys given.');
        }

        $keyX   = array();
        $keyY   = array();
        $keyLen = 0;
        $threshold = 0;

        foreach ($keys as $key) {
            if( $threshold === 0 ) {
                $threshold = (int)substr($key, 0, 2);
            } elseif( $threshold != (int)substr($key, 0, 2) ) {
                throw new \RuntimeException( 'Given keys are incompatible.' );
            } elseif ( $threshold < count($keys) ) {
                throw new \RuntimeException( 'Not enough keys to disclose secret.' );
            }
            $keyX[] = (int)substr($key, 2, 2);
            $key    = substr($key, 4);
            if( $keyLen === 0 ) {
                $keyLen = strlen($key);
            } elseif( $keyLen != strlen($key) ) {
                throw new \RuntimeException( 'Given keys vary in key length.' );
            }
            for ($i = 0; $i < strlen($key); $i += 2) {
                $keyY[] = self::convBase(substr($key, $i, 2), self::keyChars, self::decimal);
            }
        }

        $keyLen /= 2;

        $coefficients = self::reverseCoefficients($keyX, $threshold);

        $secret = "";
        for ($i = 0; $i < $keyLen; $i++) {
            $temp = 0;
            for ($j = 0; $j < $threshold; $j++) {
                $temp = self::modulo(
                    $temp + $keyY[$keyLen * $j + $i] * $coefficients[$j]
                );
            }
            $secret .= chr($temp);
        }

        return $secret;
    }
}