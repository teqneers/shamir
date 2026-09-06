# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

PHP library implementing Shamir's Secret Sharing algorithm. Splits a secret into `N` shares where any `threshold` number of shares can reconstruct the original. Also provides a CLI tool via `bin/shamir.php`.

**Requirements:** PHP >= 8.2, `ext-bcmath` (required), `ext-openssl` (optional, better randomness)

## Commands

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/SecretTest.php

# Static analysis (level 4, config in phpstan.neon.dist)
vendor/bin/phpstan analyse --memory-limit=512M

# Test with lowest-compatible dependency versions
composer update --prefer-lowest && vendor/bin/phpunit
```

## Architecture

The library uses a **static facade + strategy pattern**:

- `src/Secret.php` — Static facade; primary public API. Wraps `Shamir` with `share()` and `recover()` methods. Supports injection of custom `Algorithm` and `Generator` implementations.
- `src/Algorithm/Shamir.php` — Core algorithm: polynomial construction over a finite field, Horner's method for evaluation, Lagrange interpolation for recovery. Uses BCMath for arbitrary-precision arithmetic.
- `src/Algorithm/ExtendableAlgorithm.php` — optional capability interface for issuing further shares of an already divided secret (`Shamir::addShares()`); kept out of `Algorithm` so existing implementations stay valid.
- `src/Random/` — `Generator` interface with two implementations: `PhpGenerator` (default) and `OpenSslGenerator`.
- `src/Console/` — Symfony Console commands (`ShareCommand`, `RecoverCommand`) wired to `bin/shamir.php`.

The algorithm encodes secrets using a custom base-45 alphabet (`0-9a-z.,:;-+*#%`), chunks the input, picks a prime larger than the chunk, and builds a random polynomial of degree `threshold - 1`.

## CI

GitHub Actions (`.github/workflows/ci.yml`) runs two jobs: a matrix of PHP 8.2–8.5 × lowest/highest dependencies, and a PHPStan pass. There is no external coverage or quality service - Scrutinizer and Code Climate were both removed after their pinned toolchains stopped installing.

`tests/LegacyShareTest.php` guards the share format against change, using fixtures captured from releases 1.1.0 and 2.0.1. A failure there means previously issued shares can no longer be recovered.