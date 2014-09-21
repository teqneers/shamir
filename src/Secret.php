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
     * @var int needs to be a prime > max(2^8, $number)
     */
    const Q = 257;

    /**
     * @var array Cache of the inverse table.
     */
    private static $invTab;

    /**
     * Calculates $number % self::Q
     *
     * @param int $number
     * @return int
     */
    private static function modQ($number)
    {
        $mod = $number % self::Q;

        return ($mod < 0) ? $mod + self::Q : $mod;
    }

    /**
     * Calculates the a lookup table for reverse coefficients
     *
     * @return array
     */
    private static function invTab()
    {
        if (!isset(self::$invTab)) {
            $x            = $y = 1;
            self::$invTab = array(0 => 0);
            for ($i = 0; $i < self::Q; $i++) {
                self::$invTab[$x] = $y;
                $x                = self::modQ(3 * $x);
                $y                = self::modQ(86 * $y);
            }
        }

        return self::$invTab;
    }

    /**
     * Applies the horner schema to x using the coefficients
     *
     * @param int   $x
     * @param array $coefficients
     * @return int
     */
    private static function horner($x, array $coefficients)
    {
        $val = 0;
        foreach ($coefficients as $c) {
            $val = self::modQ($x * $val + $c);
        }

        return $val;
    }

    /**
     * Calculates the inverse modulo
     *
     * @param int $i
     * @return int
     */
    private static function inv($i)
    {
        $invTab = self::invTab();

        return ($i < 0) ? self::modQ(-$invTab[-$i]) : $invTab[$i];
    }

    /**
     * Returns an array of random coefficients
     *
     * @param int $quorum
     * @return array
     */
    private static function coefficients($quorum)
    {
        $coefficients = array();
        for ($i = 0; $i < $quorum - 1; $i++) {
            $coefficients[] = self::modQ(mt_rand(0, 65535));
        }

        return $coefficients;
    }

    /**
     * Calculates the reverse coefficients
     *
     * @param array $keyX
     * @param int   $quorum
     * @return array
     * @throws \RuntimeException
     */
    private static function reverseCoefficients(array $keyX, $quorum)
    {
        $coefficients = array();

        for ($i = 0; $i < $quorum; $i++) {
            $temp = 1;
            for ($j = 0; $j < $quorum; $j++) {
                if ($i != $j) {
                    $temp = self::modQ(
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
     * Calculates the secret value
     *
     * @param int $byte
     * @param int $number
     * @param int $quorum
     * @return array
     */
    private static function calculateSecret($byte, $number, $quorum)
    {
        $coefficients   = self::coefficients($quorum);
        $coefficients[] = $byte;

        $result = array();
        for ($i = 0; $i < $number; $i++) {
            $result[] = self::horner($i + 1, $coefficients);
        }

        return $result;
    }

    /**
     * Creates the shared secrets
     *
     * @param string   $secret
     * @param int      $number
     * @param int|null $quorum
     * @return array
     * @throws \OutOfBoundsException
     */
    public static function share($secret, $number, $quorum = null)
    {
        if ($number > self::Q - 1 || $number < 0) {
            throw new \OutOfBoundsException("Number ($number) needs to be between 0 and " . (self::Q - 1));
        }

        if (is_null($quorum)) {
            $quorum = floor($number / 2) + 1;
        } elseif ($quorum > $number) {
            throw new \OutOfBoundsException("Quorum ($quorum) cannot exceed number ($number)");
        }

        $result = array();

        foreach (unpack("C*", $secret) as $byte) {
            foreach (self::calculateSecret($byte, $number, $quorum) as $subResult) {
                $result[] = $subResult;
            }
        }

        $keys = array();

        for ($i = 0; $i < $number; $i++) {
            $key = sprintf("%02x%02x", $quorum, $i + 1);
            for ($j = 0; $j < strlen($secret); $j++) {
                $key .= ($result[$j * $number + $i] == 256) ? "g0" : sprintf("%02x", $result[$j * $number + $i]);
            }
            $keys[] = substr($key, 0);
        }

        return $keys;
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
                $temp = self::modQ(
                    $temp + $keyY[$keyLen * $j + $i] * $coefficients[$j]
                );
            }
            $secret .= chr($temp);
        }

        return $secret;
    }
}