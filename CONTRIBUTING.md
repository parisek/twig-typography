# Contributing

Thank you for your help. This guide is short. [AGENTS.md](AGENTS.md) has the full operational notes.

## Set up

```bash
composer install
```

PHP 8.3 or newer is required.

## Check your change

```bash
composer check            # cs, test, phpstan
composer normalize:check  # composer.json is normalized
composer validate --strict
composer audit            # dependency advisories
```

CI runs these checks on a PHP, Twig and Symfony matrix. A green `composer check` clears the fast part only.

## Pull requests

- Add or update a test with your change.
- Use a Conventional Commit as the PR title. Allowed types: `feat`, `fix`, `docs`, `chore`, `ci`, `test`, `refactor`, `qa`. A scope is optional.
- Add an entry under `## [Unreleased]` in `CHANGELOG.md` for every behavior-affecting change. Use the [Keep a Changelog](https://keepachangelog.com/) categories.
- Update `README.md` when the public behavior changes.
- Maintainers squash-merge into `main`. The PR title becomes the commit subject and ends with `(#N)`.

## Decisions

Record a lasting design decision as an ADR in `docs/adr/`.

## Releases

Maintainers release with the `Stamp Release` workflow. Do not tag a release by hand.

## Security

Do not report vulnerabilities in a public issue. Follow [SECURITY.md](SECURITY.md).

## AI agents

Read [AGENTS.md](AGENTS.md) first. It is the source of truth for agents.
