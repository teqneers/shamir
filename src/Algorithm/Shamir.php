<?php

namespace TQ\Shamir\Algorithm;

use OutOfRangeException;
use RuntimeException;
use TQ\Shamir\Random\Generator;
use TQ\Shamir\Random\PhpGenerator;

/**
 * Class Shamir
 *
 * Based on "Shamir's Secret Sharing class" from Kenny Millington
 *
 * @link    https://www.kennynet.co.uk/misc/shamir.class.txt
 *
 * @package TQ\Shamir\Algorithm
 */
class Shamir implements Algorithm, RandomGeneratorAware
{
    /**
     * Calculation base (decimal)
     *
     * Changing this will invalid all previously created keys.
     *
     * @const   string
     */
    protected const DECIMAL = '0123456789';

    /**
     * Target base characters to be used in passwords (shares)
     *
     * The more characters are used, the shorter the shares might get.
     * Changing this will invalid all previously created keys.
     *
     * @const   string
     */
    protected const CHARS = '0123456789abcdefghijklmnopqrstuvwxyz.,:;-+*#%';

    /**
     * Character to fill up the secret keys
     *
     * @const   string
     */
    protected const PAD_CHAR = '=';

    /**
     * Prime number has to be greater than the maximum number of shares possible
     *
     * @var float
     */
    protected $prime = 257;

    /**
     * Chunk size in bytes
     *
     * The secret will be divided equally. This value defines the chunk size and
     * how many bytes will get encoded at once.
     *
     * @var int
     */
    protected $chunkSize = 1;

    /**
     * The random generator
     *
     * @var Generator
     */
    protected $randomGenerator;

    /**
     * Maximum number of shares required
     *
     * @var float
     */
    protected $maxShares = 3;

    /**
     * @inheritdoc
     */
    public function getRandomGenerator(): Generator
    {
        if (!$this->randomGenerator) {
            $this->randomGenerator = new PhpGenerator();
        }

        return $this->randomGenerator;
    }

    /**
     * @inheritdoc
     * @return Shamir
     */
    public function setRandomGenerator(Generator $generator): Shamir
    {
        $this->randomGenerator = $generator;

        return $this;
    }

    /**
     * Returns chunk size in bytes
     *
     * @return int
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Sets chunk size in bytes
     *
     * If maximum shares have been set already, the chunk
     * size might have been set with it. It is not possible
     * to set a smaller size than required by shares.
     *
     * @see
     * @param  int  $chunkSize  Size in number of bytes
     * @return Shamir
     * @throws OutOfRangeException
     */
    public function setChunkSize(int $chunkSize): Shamir
    {
        $primeNumber = [1 => 257, 65537, 16777259, 4294967311, 1099511627791, 281474976710677, 72057594037928017];

        if (!isset($primeNumber[$chunkSize])) {
            throw new OutOfRangeException(
                'Chunk size with '.$chunkSize.' bytes is not allowed. Use 1 to '.count($primeNumber).'.'
            );
        }

        $this->chunkSize = $chunkSize;
        // if chunk size has been set already, we will only increase it, if necessary
        $this->prime = $primeNumber[$chunkSize];

        return $this;
    }

    /**
     * Configure encoding parameters
     *
     * Depending on the number of required shares, we need to change
     * prime number, key length, chunk size and more.
     *
     * If the chunk size has been set already, it will be changed, if
     * it is smaller than the necessary size.
     *
     * @see    setChunkSize()
     * @param  int  $max  Maximum number of keys needed
     * @return Shamir
     * @throws OutOfRangeException
     */
    protected function setMaxShares(int $max): Shamir
    {
        // the prime number has to be larger, than the maximum number
        // representable by the number of bytes. so we always need one
        // byte more for encryption. if someone wants to use 256 shares,
        // we could encrypt 256 with a single byte, but due to encrypting
        // with a bigger prime number, we will need to use 2 bytes.

        // max possible number of shares is the maximum number of bytes
        // possible to be represented with max integer, but we always need
        // to save one byte for encryption.
        $maxPossible = 1 << (PHP_INT_SIZE - 1) * 8;

        if ($max > $maxPossible) {
            // we are unable to provide more bytes-1 as supported by OS
            // because the prime number need to be higher than that, but
            // this would exceed OS integer range.
            throw new OutOfRangeException(
                'Number of required keys has to be below '.number_format($maxPossible).'.'
            );
        }

        // calculate how many bytes we need to represent number of shares.
        // e.g. everything less than 256 needs only a single byte.
        $chunkSize = (int)ceil(log($max, 2) / 8);
        // if chunk size has been set already, we will only increase it, if necessary
        $chunkSize = max($chunkSize, $this->chunkSize);

        if ($chunkSize > $this->chunkSize) {
            $this->setChunkSize($chunkSize);
        }

        $this->maxShares = $max;

        return $this;
    }

    /**
     * Calculate modulo of any given number using prime
     *
     * @param  string     Number
     * @return int     Module of number
     */
    protected function modulo(string $number): int
    {
        $modulo = bcmod($number, $this->prime);

        return ($modulo < 0) ? bcadd($modulo, $this->prime) : $modulo;
    }

    /**
     * Returns decomposition of the greatest common divisor of a and b
     *
     * @param  int     $a
     * @param  string  $b
     * @return array
     */
    protected function gcdD(int $a, string $b): array
    {
        if ($b === '0') {
            return [$a, 1, 0];
        }

        $div    = floor(bcdiv($a, $b));
        $mod    = bcmod($a, $b);
        $decomp = $this->gcdD($b, $mod);

        return [$decomp[0], $decomp[2], $decomp[1] - $decomp[2] * $div];
    }

    /**
     * Calculates the inverse modulo
     *
     * @param  int  $number
     * @return string
     */
    protected function inverseModulo(int $number): string
    {
        $mod = bcmod($number, $this->prime);
        $r   = $this->gcdD($this->prime, abs($mod));
        $r   = ($mod < 0) ? -$r[2] : $r[2];

        return bcmod(bcadd($this->prime, $r), $this->prime);
    }

    /**
     * Calculates the reverse coefficients
     *
     * @param  array  $keyX
     * @param  int    $threshold
     * @return array
     * @throws RuntimeException
     */
    protected function reverseCoefficients(array $keyX, int $threshold): array
    {
        $coefficients = [];

        for ($i = 0; $i < $threshold; $i++) {
            $temp = 1;
            for ($j = 0; $j < $threshold; $j++) {
                if ($i !== $j) {
                    $temp = $this->modulo(
                        bcmul(bcmul(-$temp, $keyX[$j]), $this->inverseModulo($keyX[$i] - $keyX[$j]))
                    );
                }
            }

            if ($temp === 0) {
                /* Repeated share */
                throw new RuntimeException('Repeated share detected - cannot compute reverse-coefficients');
            }

            $coefficients[] = $temp;
        }

        return $coefficients;
    }

    /**
     * Generate random coefficients
     *
     * @param  int  $threshold  Number of coefficients needed
     *
     * @return array
     */
    protected function generateCoefficients(int $threshold): array
    {
        $coefficients = [];
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
     * @see    https://en.wikipedia.org/wiki/Horner%27s_method
     * @param  int    $xCoordinate   X coordinate
     * @param  array  $coefficients  Polynomial coefficients
     * @return int                   Y coordinate
     */
    protected function hornerMethod(int $xCoordinate, array $coefficients): int
    {
        $yCoordinate = 0;
        foreach ($coefficients as $coefficient) {
            $yCoordinate = $this->modulo($xCoordinate * $yCoordinate + $coefficient);
        }

        return $yCoordinate;
    }

    /**
     * Converts from $fromBaseInput to $toBaseInput
     *
     * @param  string  $numberInput
     * @param  string  $fromBaseInput
     * @param  string  $toBaseInput
     * @return string
     */
    protected static function convBase(string $numberInput, string $fromBaseInput, string $toBaseInput): string
    {
        if ($fromBaseInput === $toBaseInput) {
            return $numberInput;
        }
        $fromBase  = str_split($fromBaseInput, 1);
        $toBase    = str_split($toBaseInput, 1);
        $number    = str_split($numberInput, 1);
        $fromLen   = strlen($fromBaseInput);
        $toLen     = strlen($toBaseInput);
        $numberLen = strlen($numberInput);
        $retVal    = '';
        if ($toBaseInput === '0123456789') {
            $retVal = 0;
            for ($i = 1; $i <= $numberLen; $i++) {
                $retVal = bcadd(
                    $retVal,
                    bcmul(array_search($number[$i - 1], $fromBase, true), bcpow($fromLen, $numberLen - $i))
                );
            }

            return $retVal;
        }
        if ($fromBaseInput !== '0123456789') {
            $base10 = self::convBase($numberInput, $fromBaseInput, '0123456789');
        } else {
            $base10 = $numberInput;
        }
        if ($base10 < strlen($toBaseInput)) {
            return $toBase[$base10];
        }
        while ($base10 !== '0') {
            $retVal = $toBase[bcmod($base10, $toLen)].$retVal;
            $base10 = bcdiv($base10, $toLen, 0);
        }

        return $retVal;
    }

    /**
     * Unpack a binary string and convert it into decimals
     *
     * Convert each chunk of a binary data into decimal numbers.
     *
     * @param  string  $string  Binary string
     * @return array            Array with decimal converted numbers
     */
    protected function unpack(string $string): array
    {
        $chunk  = 0;
        $int    = null;
        $return = [];
        foreach (unpack('C*', $string) as $byte) {
            $int = bcadd($int, bcmul($byte, bcpow(2, $chunk * 8)));
            if (++$chunk === $this->chunkSize) {
                $return[] = $int;
                $chunk    = 0;
                $int      = null;
            }
        }
        if ($chunk > 0) {
            $return[] = $int;
        }

        return $return;
    }

    /**
     * Returns maximum length of converted string to new base
     *
     * Calculate the maximum length of a string, which can be
     * represented with the number of given bytes and convert
     * its base.
     *
     * @param  int  $bytes  Bytes used to represent a string
     * @return int          Number of chars
     */
    protected function maxKeyLength(int $bytes): int
    {
        $maxInt    = bcpow(2, $bytes * 8);
        $converted = self::convBase($maxInt, self::DECIMAL, self::CHARS);

        return strlen($converted);
    }

    /**
     * Divide secret into chunks and calculate coordinates
     *
     * @param  string  $secret     Secret
     * @param  int     $shares     Number of parts to share
     * @param  int     $threshold  Minimum number of shares required for decryption
     * @return array
     */
    protected function divideSecret(string $secret, int $shares, int $threshold): array
    {
        // divide secret into chunks, which we encrypt one by one
        $result = [];

        foreach ($this->unpack($secret) as $bytes) {
            $coeffs   = $this->generateCoefficients($threshold);
            $coeffs[] = $bytes;

            // go through x coordinates and calculate y value
            for ($x = 1; $x <= $shares; $x++) {
                // use horner method to calculate y value
                $result[] = $this->hornerMethod($x, $coeffs);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function share($secret, $shares, $threshold = 2): array
    {
        $this->setMaxShares($shares);

        // check if number of shares is less than our prime, otherwise we have a security problem
        if ($shares >= $this->prime || $shares < 1) {
            throw new OutOfRangeException('Number of shares has to be between 1 and '.$this->prime.'.');
        }

        if ($shares < $threshold) {
            throw new OutOfRangeException('Threshold has to be between 1 and '.$shares.'.');
        }

        if (strpos(self::CHARS, self::PAD_CHAR) !== false) {
            throw new OutOfRangeException('Padding character must not be part of possible encryption chars.');
        }

        // divide secret into chunks, which we encrypt one by one
        $result = $this->divideSecret($secret, $shares, $threshold);

        // encode number of bytes and threshold

        // calculate the maximum length of key sequence number and threshold
        $maxBaseLength = $this->maxKeyLength($this->chunkSize);
        // in order to do a correct padding to the converted base, we need to use the first char of the base
        $paddingChar = substr(self::CHARS, 0, 1);
        // define prefix number using the number of bytes (hex), and a left padded string used for threshold (base converted)
        $fixPrefixFormat = '%x%'.$paddingChar.$maxBaseLength.'s';
        // prefix is going to be the same for all keys
        $prefix = sprintf($fixPrefixFormat, $this->chunkSize, self::convBase($threshold, self::DECIMAL, self::CHARS));

        // convert y coordinates into hexadecimals shares
        $passwords = [];
        $secretLen = strlen($secret);
        // calculate how many bytes, we need to cut off during recovery
        if ($secretLen % $this->chunkSize > 0) {
            $tail = str_repeat(self::PAD_CHAR, $this->chunkSize - $secretLen % $this->chunkSize);
        } else {
            $tail = '';
        }

        $chunks = ceil($secretLen / $this->chunkSize);
        for ($i = 0; $i < $shares; ++$i) {
            $sequence = self::convBase(($i + 1), self::DECIMAL, self::CHARS);
            $key      = sprintf($prefix.'%'.$paddingChar.$maxBaseLength.'s', $sequence);

            for ($j = 0; $j < $chunks; ++$j) {
                $key .= str_pad(
                    self::convBase($result[$j * $shares + $i], self::DECIMAL, self::CHARS),
                    $maxBaseLength,
                    $paddingChar,
                    STR_PAD_LEFT
                );
            }
            $passwords[] = $key.$tail;
        }

        return $passwords;
    }

    /**
     * Decode and merge secret chunks
     *
     * @param  array  $keyX       Keys for X coordinates
     * @param  array  $keyY       Keys for Y coordinates
     * @param  int    $bytes      Chunk size in bytes
     * @param  int    $keyLen     Key length in chunks
     * @param  int    $threshold  Minimum number of shares required for decryption
     * @return string
     */
    protected function joinSecret(array $keyX, array $keyY, int $bytes, int $keyLen, int $threshold): string
    {
        $coefficients = $this->reverseCoefficients($keyX, $threshold);

        $secret = '';
        for ($i = 0; $i < $keyLen; $i++) {
            $temp = 0;
            for ($j = 0; $j < $threshold; $j++) {
                $temp = $this->modulo(
                    bcadd($temp, bcmul($keyY[$j * $keyLen + $i], $coefficients[$j]))
                );
            }
            // convert each byte back into char
            for ($byte = 1; $byte <= $bytes; ++$byte) {
                $char   = bcmod($temp, 256);
                $secret .= chr($char);
                $temp   = bcdiv(bcsub($temp, $char), 256);
            }
        }

        return $secret;
    }

    /**
     * @inheritdoc
     */
    public function recover(array $keys): string
    {
        if (!count($keys)) {
            throw new RuntimeException('No keys given.');
        }

        $keyX      = [];
        $keyY      = [];
        $keyLen    = null;
        $threshold = null;

        // analyse first key
        $key = reset($keys);
        // first we need to find out the bytes to predict threshold and sequence length
        $bytes = hexdec(substr($key, 0, 1));
        $this->setChunkSize($bytes);
        // calculate the maximum length of key sequence number and threshold
        $maxBaseLength = $this->maxKeyLength($bytes);
        // define key format: bytes (hex), threshold, sequence, and key (except of bytes, all is base converted)
        $keyFormat = '%1x%'.$maxBaseLength.'s%'.$maxBaseLength.'s%s';

        foreach ($keys as $key) {
            // remove trailing padding characters
            $key = str_replace(self::PAD_CHAR, '', $key);

            // extract "public" information of key: bytes, threshold, sequence

            [$keyBytes, $keyThreshold, $keySequence, $key] = sscanf($key, $keyFormat);
            $keyThreshold = (int)self::convBase($keyThreshold, self::CHARS, self::DECIMAL);
            $keySequence  = (int)self::convBase($keySequence, self::CHARS, self::DECIMAL);

            if ($threshold === null) {
                $threshold = $keyThreshold;

                if ($threshold > count($keys)) {
                    throw new RuntimeException('Not enough keys to disclose secret.');
                }
            } elseif ($threshold !== $keyThreshold || $bytes != hexdec($keyBytes)) {
                throw new RuntimeException('Given keys are incompatible.');
            }

            $keyX[] = $keySequence;
            if ($keyLen === null) {
                $keyLen = strlen($key);
            } elseif ($keyLen !== strlen($key)) {
                throw new RuntimeException('Given keys vary in key length.');
            }
            for ($i = 0; $i < $keyLen; $i += $maxBaseLength) {
                $keyY[] = self::convBase(substr($key, $i, $maxBaseLength), self::CHARS, self::DECIMAL);
            }
        }

        $keyLen /= $maxBaseLength;
        $secret = $this->joinSecret($keyX, $keyY, $bytes, $keyLen, $threshold);

        // remove padding from secret (NULL bytes);
        $padCount = substr_count(reset($keys), self::PAD_CHAR);
        if ($padCount) {
            $secret = substr($secret, 0, -1 * $padCount);
        }

        return $secret;
    }
}
