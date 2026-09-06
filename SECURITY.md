# Security Policy

## Reporting a vulnerability

Please do **not** open a public issue for a security problem.

Use GitHub's private reporting instead: go to the
[Security tab](https://github.com/teqneers/shamir/security) and choose
*Report a vulnerability*. That opens a private advisory visible only to the
maintainers.

Useful things to include: the affected version, the PHP version, and the smallest
piece of code or command that shows the problem. Please do not include a real
secret or real shares in the report.

## Supported versions

| Release | Supported          | PHP           |
|---------|--------------------|---------------|
| 2.1.x   | Yes                | 8.2 and above |
| 2.0.x   | Security fixes     | 8.1 and above |
| 1.1.x   | No                 | 7.2 up to 8.1 |

Older releases stay on Packagist so that secrets shared long ago can still be
recovered on the PHP version they were created for. They receive no fixes.

## Using this library safely

A few properties are worth knowing before relying on it.

**Shares are not encrypted.** Shamir's scheme hides the secret only while fewer
than `threshold` shares are combined. Anyone holding `threshold` shares can
reconstruct the secret, so shares must be distributed and stored as carefully as
the secret itself.

**Do not pass a secret as a command line argument.** `shamir:share "my secret"`
puts the secret into the process list, where other users on the machine can read
it, and usually into the shell history too. The command prints a warning when
used this way. Prefer `--file`, or piping the secret on standard input, or the
interactive prompt.

**Adding shares is as sensitive as recovery.** `Secret::addShares()` needs
`threshold` existing shares to issue more, which is by definition enough to
reconstruct the secret. It also trusts the caller's statement of the highest
share number issued so far. Understating it re-issues an existing number, which
yields a byte-identical copy of a share already in circulation - recovery never
returns a wrong secret, but two holders end up with the same share, so the set
contains fewer distinct shares than the share count suggests.

**Randomness matters.** Shares are generated from random polynomial
coefficients. The default `PhpGenerator` uses `random_int()`, which draws from
the system CSPRNG. `OpenSslGenerator` is available as an alternative. A custom
`Generator` passed to `Secret::setRandomGenerator()` is entirely your
responsibility - a weak or predictable one undermines the whole scheme.

**The share format is fixed.** Shares created by any released version can be
recovered by any other, and the test suite enforces this. If you are considering
a change to the encoding, note that it would orphan every share ever issued.
