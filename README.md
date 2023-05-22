Shamir's Secret Sharing in PHP
==============================

Build status: [![Build Status](https://travis-ci.org/teqneers/shamir.svg)](https://travis-ci.org/teqneers/shamir)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/teqneers/shamir/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/teqneers/shamir/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/teqneers/shamir/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/teqneers/shamir/?branch=master)
[![Code Climate](https://codeclimate.com/github/teqneers/shamir/badges/gpa.svg)](https://codeclimate.com/github/teqneers/shamir)

Project information: [![License](https://img.shields.io/github/license/teqneers/shamir.svg?style=flat)](https://img.shields.io/github/license/teqneers/shamir.svg?style=flat)
[![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/teqneers/shamir.svg?style=flat)]((https://img.shields.io/github/languages/code-size/teqneers/shamir.svg?style=flat))
[![PHP Versions tested on Travis](https://img.shields.io/travis/php-v/teqneers/shamir.svg?style=flat)](https://img.shields.io/travis/php-v/teqneers/shamir.svg?style=flat)

This is Shamir's Shared Secret implementation in PHP. It allows you to create shared secrets using the PHP classes or the CLI interface.

The cryptographic algorithm was created by the famous Adi Shamir, who also provided his name to the Rivest-__Shamir__-Adleman cryptosystem (RSA). The Shared Secret algorithm allows to divide a secret into parts (called shares). Each part can be handed out to a person or organization. The nice thing about this algorithm is, that some or all parts are needed to reconstruct the secret (called threshold). Most important about a secure way of [sharing a secret](http://en.wikipedia.org/wiki/Secret_sharing) is, that it complies to the following requirement:

* exposure of one share does NOT expose any more hints to an attacker
* predefined number of shares are required to obtain secret
* knowledge of all-but-one no better than knowing none
* shares combined have same magnitude in length than secret itself

This implementation can handle more than 256 shares and encodes the results in a compressed, but readable way.


Usage
=====

```bash
# bin/shamir.php
Shamir's Shared Secret CLI 1.1.0

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help            Displays help for a command
  list            Lists commands
 shamir
  shamir:recover  Recover a shared secret
  shamir:share    Create a shared secret
```

CLI Examples
============
```shell
# bin/shamir.php shamir:share
The secret to share: Share my secret
Number of shared secrets to create [3]:
Number of shared secrets required [2]:

  102014%5g0m1p21485434261-3t215p3k3+
  102022i2v4o0q1.1*223m3p1a521:2;4t5b
  102035o012;5e1q5i4w3-4%0p0x1u08060;

# bin/shamir.php shamir:recover
Shared secret [empty to stop]: 102014%5g0m1p21485434261-3t215p3k3+
Shared secret [empty to stop]: 102035o012;5e1q5i4w3-4%0p0x1u08060;
Shared secret [empty to stop]:

  Share my secret

# bin/shamir.php shamir:share "Share my secret"

  10201241j1x042l0m1j3n530c16123m1w3r
  102022f0o1e3g2v0c0j4f3w3v015r4k184s
  102032q5g0-0+2+0256572g1i4s4k5i0t5t

# echo -n "Share my secret" | bin/shamir.php shamir:share

  102014k3:4:371u0i042i0p343t1i0h1l55
  102021f5h1,3z14043l250i3s520r3*0v1*
  102033*194n4i0n5m161,0b470x5n1z5s4l

# bin/shamir.php shamir:share -f path/to/secretFile

  10201010-2p4+1:1c4947512b2-194,2,4*
  102023+582#1q1k1,0c5s3s1*3o091i3i1q
  10203243y3g3#122h221h2a1s484+3v3%3*

# bin/shamir.php shamir:recover "10201241j1x042l0m1j3n530c16123m1w3r" "102032q5g0-0+2+0256572g1i4s4k5i0t5t"

  Share my secret

# bin/shamir.php shamir:share -s 4 -t 3 "Share my secret"

  10301043625274u011,0910183+0.112e2*
  103021f0c2t5f1k3s494w1;221x4%3o1k0a
  103035q563y0t40043:4.3e571j3e4g5g07
  103041i0r5k5a0+0:0y0l5f4.2%1n3m2s2x

# bin/shamir.php shamir:recover "10301043625274u011,0910183+0.112e2*" "103035q563y0t40043:4.3e571j3e4g5g07" "103041i0r5k5a0+0:0y0l5f4.2%1n3m2s2x"

  Share my secret

```

PHP Examples
============
```php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use TQ\Shamir\Secret;

$shares = Secret::share('Shamir\'s Shared Secret Implementation in PHP', 5, 2);

var_dump($shares);

var_dump(Secret::recover(array_slice($shares, 0, 2)));
var_dump(Secret::recover(array_slice($shares, 1, 3)));
```

Requirements
============

PHP >= 7.2.0 and <= 8.1.x

Integrations
============

Tiki Wiki CMS Groupware: https://doc.tiki.org/Shared-Secret


License
=======

Shamir's Secret Sharing in PHP is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
