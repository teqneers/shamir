# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Shares are portable across every release: a share created by any version listed here
can be recovered by any other. `tests/LegacyShareTest.php` enforces this.

## [Unreleased]

### Fixed

- `shamir:share --file` with an unreadable file no longer calls `exit()`, which
  terminated any process embedding the command. It now reports
  `ERROR: file "..." is not readable.` on the error output and returns exit code 1.

### Changed

- Unit tests for the console commands (`tests/ConsoleCommandTest.php`), covering
  invalid input for `shamir:share`, `shamir:recover` and `shamir:add`.
- PHPUnit no longer reports deprecations raised inside `vendor/`, so lowest-dependency
  runs on current PHP show only deprecations triggered by this package.

## [2.2.0] - 2026-09-06

### Added

- `Secret::addShares($keys, $additional, $highestSequence)` issues further shares for
  an already divided secret without supplying the secret again. Added shares work with
  the existing ones and are byte-for-byte the shares that would have been issued at
  those positions originally ([#3](https://github.com/teqneers/shamir/issues/3)).
- `shamir:add` CLI command doing the same, taking existing shares as arguments, from
  `--file`, or interactively.
- `ExtendableAlgorithm` interface for that capability. `Algorithm` is unchanged, so
  existing implementations stay valid.

### Fixed

- `shamir:recover` with `--no-interaction` and empty input no longer emits a
  `trim(): Passing null` deprecation (a `TypeError` in PHP 9). It now reports
  `ERROR: no shares given.` and exits 1.
- `shamir:add` reports caller mistakes as plain errors instead of stack traces.

## [2.1.0] - 2026-09-06

### Added

- Support for `symfony/console` ^8.0, alongside ^6.4.3 and ^7.0.
- Warning on the error output when `shamir:share` receives the secret as a command
  argument, since it is visible to other users in process listings.
- `SECURITY.md` covering private vulnerability reporting.
- Share-format regression tests: shares captured from 1.1.0 and 2.0.1 are recovered on
  every run, and known-answer vectors pin the encoder output.

### Fixed

- `shamir:share "my secret"` no longer fails with a `TypeError` when STDIN is not a
  terminal and already at EOF (cron, CI, `docker run` without `-t`). Running out of
  input now reports `ERROR: no secret given.` and exits 1.
- Chunk size no longer leaks between calls. A single many-share operation used to
  permanently enlarge the shares produced by every later `share()` in the same
  process. An explicit `setChunkSize()` is still respected.
- `Secret::setRandomGenerator()` and `Secret::setAlgorithm()` declare their nullable
  parameters explicitly, avoiding the PHP 8.4 implicit-nullable deprecation.

### Changed

- **Requires PHP 8.2 or above.** PHP 8.1 reached end of life on 2025-12-31.
- `symfony/console` floor raised to ^6.4.3; ^5.0 is no longer supported.
- Development: `phpunit/phpunit` ^11.5 || ^12.0 || ^13.0, PHPStan added.
- CI runs PHP 8.2 to 8.5 against lowest and highest dependencies. Travis CI, Code
  Climate and Scrutinizer were removed.
- The distributed archive no longer contains CI, editor or agent configuration.

## [2.0.1] - 2023-12-22

### Added

- Support for `symfony/console` ^7.0.

### Changed

- `shamir:share --file` now takes precedence over data on STDIN.
- Development: `phpunit/phpunit` ^10.5, GitHub Actions CI with coverage, Dependabot.

## [2.0.0] - 2023-12-21

### Changed

- **Requires PHP 8.1 or above.** PHP 7.x and 8.0 are no longer supported.
- `symfony/console` ^5.0 || ^6.0; ^2.0, ^3.0 and ^4.0 are no longer supported.
- Development: `phpunit/phpunit` ^10.0.
- Docker environment file renamed to `docker/compose.yaml`.

## [1.1.0] - 2023-05-22

First tagged release. Earlier development (2014 to 2020) was not tagged.

### Added

- `shamir:share` and `shamir:recover` CLI commands.
- `OpenSslGenerator` as an alternative random generator to `PhpGenerator`.
- Docker Compose development environment.

### Fixed

- PHP 8.1 deprecation from passing `null` to `bcadd()`.

### Requirements

- PHP 7.2 up to 8.1, `symfony/console` ^2.0 || ^3.0 || ^4.0 || ^5.0.

[Unreleased]: https://github.com/teqneers/shamir/compare/2.2.0...HEAD
[2.2.0]: https://github.com/teqneers/shamir/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/teqneers/shamir/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/teqneers/shamir/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/teqneers/shamir/compare/1.1.0...2.0.0
[1.1.0]: https://github.com/teqneers/shamir/releases/tag/1.1.0
