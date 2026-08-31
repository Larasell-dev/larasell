# Security Policy

## Supported Versions

Larasell is currently under active development. Security fixes are provided for
the latest released version only. Users should upgrade to the latest release
before reporting a vulnerability or requesting a security fix.

Pre-release versions and unreleased code on the default branch are not covered
by a long-term support commitment.

## Reporting a Vulnerability

Do not report security vulnerabilities in public GitHub issues, discussions, or
pull requests.

Report vulnerabilities privately through
[GitHub Security Advisories](https://github.com/Larasell-dev/larasell/security/advisories/new).
If that channel is unavailable, email [nils@larasell.dev](mailto:nils@larasell.dev).

Include enough information to reproduce and assess the issue:

- The affected Larasell and Laravel versions
- A description of the vulnerability and its impact
- Reproduction steps or a minimal proof of concept
- Any known mitigations or suggested fixes
- Whether the issue has been disclosed elsewhere

You should receive an acknowledgement within 5 business days. After triage, we
will share the assessment and, when applicable, an expected remediation
timeline. Resolution time depends on severity and complexity.

Please allow reasonable time for a fix and coordinated release before publicly
disclosing the vulnerability. We will credit reporters in the advisory and
release notes unless anonymity is requested.

## Scope

This policy covers vulnerabilities in Larasell code maintained in this
repository. Vulnerabilities in Laravel, PHP, Composer dependencies, payment
providers, or an application's own integration should be reported to the
responsible project or vendor.

Questions about configuration, suspected bugs without a security impact, and
general support requests may be filed as regular GitHub issues.
