# Security policy

## Supported versions

Only the latest release gets security fixes. That is the newest minor release of the newest major version. Older releases get no fixes. To receive a fix, update to the latest release.

## Report a vulnerability

Do not open a public issue.

1. Open the [Security tab](https://github.com/parisek/twig-typography/security) of this repository.
2. Click "Report a vulnerability".

You can also use the direct link: <https://github.com/parisek/twig-typography/security/advisories/new>.

We aim to reply within 7 days.

## What to include

- The affected version of `parisek/twig-typography`.
- The PHP, Twig and Symfony YAML versions you use.
- Steps to reproduce the problem.
- The impact you see, for example unescaped output or a crash.

## How we fix it

We ship the fix as a patch release. We publish a GitHub Security Advisory with the release.

A problem in a dependency, such as `mundschenk-at/php-typography`, belongs to that project. Report it there.
