# AGENTS.md

Operational notes for AI coding agents (Claude Code, Codex, Cursor, …) working on this repo. Treat as authoritative — overrides default assumptions where they conflict.

Tool-specific entrypoint files (`CLAUDE.md`, `.cursorrules`, etc.) just point here so the source of truth stays in one place.

## Maintaining this file

Go-style brevity. Bullets, not paragraphs. Add only what saves the next session real time:

- **Add** a note when you hit a non-obvious gotcha or pin a convention the codebase relies on.
- **Don't add** restatement of README content, narration of what the codebase does, or one-off task context. README owns "what the project does"; AGENTS.md owns "how to work on it".
- **Cap ~150 lines.** Past that, the whole file gets skimmed instead of read.

## Project shape

A Twig extension exposing one filter, `|typography`, that wraps [`mundschenk-at/php-typography`](https://github.com/mundschenk-at/php-typography) (smart quotes, dashes, ellipses, hyphenation, widows, math symbols). The wrapper is thin; the value lives in PHP-Typography.

- `src/TypographyExtension.php` — single class, PSR-4 `Parisek\Twig\`. `final`, `declare(strict_types=1)`.
- `typography.yml` — bundled marker file. Empty by design since 1.2.0 — library `Settings(true)` defaults apply unless the consumer passes a YAML path or PHP array to the constructor.
- `tests/` — PHPUnit 11/12. `TypographyExtensionTest.php` + `tests/fixtures/` (sample configs).
- `.github/workflows/ci.yml` + `dependency-review.yml`.

Constructor accepts three shapes: `''` (library defaults), `'/path/to.yml'` (filesystem YAML), `[]` (PHP array, no filesystem I/O). Missing path falls back silently to library defaults — `parisek/styleguide` relies on this when `typography_config` resolves to a not-yet-created project file.

PHP ^8.3. Twig ^3.0 || ^4.0 (forward-compat to Twig 4 alpha; signal-only in CI). Symfony YAML 6/7/8.

## Commands

```bash
composer install
composer test           # phpunit
composer phpstan        # vendor/bin/phpstan analyse — level 8
composer validate --strict
```

## CI matrix

Three jobs in `.github/workflows/ci.yml`:

| PHP | Twig | Symfony | Required |
|---|---|---|---|
| 8.3 | ^3.0 | ^7.0 | yes |
| 8.4 | ^3.0 | ^8.0 | yes |
| 8.4 | ^4.0@alpha | ^8.0 | **signal-only** (`continue-on-error: true`) |

The Twig 4 alpha job exists to catch upstream breaks early. Don't promote it to required until Twig 4 stable lands.

PHPStan only runs on the stable jobs (`if: matrix.stable`) — the alpha job's purpose is functional regressions, not type narrowing.

## Per-PR conventions

- **CHANGELOG.md**: every behavior-affecting PR adds an entry under `## [Unreleased]` with [Keep a Changelog](https://keepachangelog.com/) categories.
- **Squash-merge PRs** into `main` so the merge commit subject ends with `(#N)`. The tag history (`v1.0`, `v1.0.1`–`v1.2.0`) is built on this convention.
- Default branch is `main`, not `master`.

## Release process

Currently manual:

1. Stamp the `[Unreleased]` heading in `CHANGELOG.md` to `[X.Y.Z] - YYYY-MM-DD`.
2. `git tag -a vX.Y.Z -m "..."` + `git push origin vX.Y.Z`.
3. Packagist auto-imports (~60s; webhook wired).
4. Create the GitHub Release (`gh release create vX.Y.Z --notes-file …`) — v1.2.0 already in place as the model.

If a release-automation workflow lands, mirror `parisek/timber-kit`'s `release-stamp.yml` + `release.yml` shape.

## PHPStan level

Level 8 (max). The package is small enough — ~95 LOC of production code — that level 8 stays clean without effort. Don't dial down; if a real type-shape problem appears, fix the code, not the level.

`tests/fixtures/*` is excluded (PHPStan would otherwise complain about fixture YAML-as-PHP shenanigans).

## Symfony YAML constraint

`symfony/yaml: ^6.0 || ^7.0 || ^8.0`. The package never deeply integrates with the Symfony container — `Yaml::parse()` is the only call site, in `loadDefaults()`. Widening to a new Symfony major is essentially free if `Yaml::parse()` keeps its signature; verify with a one-off `composer require symfony/yaml:^N` in a scratch checkout before bumping.

`parisek/styleguide` pulls both this package and `parisek/twig-attribute`. Make sure the Symfony YAML constraint here doesn't lag behind that downstream — otherwise the styleguide gets pinned to an older Symfony major than its own constraint would allow.

## Twig 4 forward-compat

The package supports `twig/twig: ^3.0 || ^4.0`. Twig 4's known breaking changes that could matter:
- `Twig\Markup` constructor stricter on `string|null` (we pass `string|\Stringable` and cast; no impact).
- Several deprecated extension hooks removed (we only implement `getFilters()`; no impact).

If Twig 4 stable lands and the alpha job's signal stays clean, drop `@alpha` from the alpha matrix entry and promote it to required.

## Style

- PSR-12, 4-space indent, `declare(strict_types=1)`, `final` by default.
- WHY-not-WHAT comments. Don't reference PRs / call sites in code comments — those rot.
- First-class callable syntax for filters: `$this->applyTypography(...)`, not `[$this, 'applyTypography']` (phpstan-friendly, idiomatic 8.1+).
