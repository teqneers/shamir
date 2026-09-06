Shamir's Secret Sharing in PHP
==============================

Build status: [![CI](https://github.com/teqneers/shamir/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/teqneers/shamir/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/teqneers/shamir/branch/master/graph/badge.svg)](https://codecov.io/gh/teqneers/shamir)

Project information: [![Latest Stable Version](https://img.shields.io/packagist/v/teqneers/shamir.svg?style=flat)](https://packagist.org/packages/teqneers/shamir)
[![PHP Version](https://img.shields.io/packagist/dependency-v/teqneers/shamir/php.svg?style=flat)](https://packagist.org/packages/teqneers/shamir)
[![Total Downloads](https://img.shields.io/packagist/dt/teqneers/shamir.svg?style=flat)](https://packagist.org/packages/teqneers/shamir)
[![License](https://img.shields.io/github/license/teqneers/shamir.svg?style=flat)](LICENSE)

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
Shamir's Shared Secret CLI 2.1.0

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
  shamir:add      Add shares to an existing shared secret
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

# add two more shares to a secret that was already split into three
# -H is the highest share number ever issued, see "Adding shares later"
# bin/shamir.php shamir:add -H 3 -s 2 "10201001v105s0#4r2z5e3y1w005o364v" "102023n0+5c2%57143e494#183n2,4114"

  102044,532h3i265m4h1%1t054,35045e
  102052s4d0*0y0s1%4+0;2:5d2s0i0%1w

# the existing shares can come from a file instead, one per line
# bin/shamir.php shamir:add -H 3 -f path/to/shares.txt

  102060j3n593,4,485k5l424y0j3i1;3,

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

Adding shares later
===================

Further shares can be issued for a secret that has already been divided, without
having the secret at hand. The new shares work with the existing ones, and with each
other.

```php
use TQ\Shamir\Secret;

$shares = Secret::share('Share my secret', 5, 2);   // s1 .. s5, two needed

// later: two more, continuing the numbering after the five already handed out
$more = Secret::addShares([$shares[0], $shares[3]], 2, 5);   // s6, s7

Secret::recover([$shares[4], $more[0]]);            // 'Share my secret'
Secret::recover($more);                             // 'Share my secret'
```

The same thing from the command line:

```shell
# bin/shamir.php shamir:add -H 5 -s 2 "10201..." "10202..."
```

Two things to be aware of.

**It takes `threshold` existing shares**, which is by definition enough to
reconstruct the secret. Whoever can add shares can already read the secret, so treat
the operation as being exactly as sensitive as recovery.

**The third argument is the highest share number ever issued**, and it cannot be
worked out from the shares you pass in - those may be any subset of what was handed
out. Understating it re-issues numbers that were used before. Because the same
polynomial is rebuilt, such a share is byte-identical to the one already in
circulation rather than a conflicting value, so nothing silently decodes to the wrong
secret: combining a duplicate with its twin throws `Repeated share detected`, and
combining it with any other share still works. The danger is operational - two people
end up holding the same share, so there are fewer distinct shares than you think. If
nobody knows how many were issued, re-share the secret instead.


Requirements
============

PHP 8.2.0 or above, with the `bcmath` extension. The `openssl` extension is optional
and provides an alternative random generator.

Older PHP versions
------------------

Shares are portable across every release of this library: a share created in 2015
still opens with the current code, and a share created today still opens on a PHP 7.2
server running 1.1.0. So if a machine cannot run the latest release, use the newest
one it supports - the secrets remain recoverable either way.

| Release | PHP           | symfony/console          |
|---------|---------------|--------------------------|
| 2.1.x   | 8.2 and above | ^6.4.3 \| ^7.0 \| ^8.0   |
| 2.0.x   | 8.1 and above | ^5.0 \| ^6.0 \| ^7.0     |
| 1.1.x   | 7.2 up to 8.1 | ^2.0 \| ^3.0 \| ^4.0 \| ^5.0 |

`composer require teqneers/shamir` resolves this automatically - an old server simply
gets an older release.

This is not a promise on paper: the test suite recovers shares captured from 1.1.0 and
2.0.1, and asserts that today's encoder still emits byte-identical output for fixed
input, so a change to the share format cannot pass CI unnoticed.

Integrations
============

Tiki Wiki CMS Groupware: https://doc.tiki.org/Shared-Secret


License
=======

Shamir's Secret Sharing in PHP is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
