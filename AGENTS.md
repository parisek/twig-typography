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
- `.github/workflows/tests.yml` + `dependency-review.yml` (`release-stamp.yml` + `release.yml` cover releases, see below).
- `docs/adr/` may be kept under `docs/` — `.gitignore` ignores `/docs/*` except `!/docs/adr/`. `.gitattributes` excludes `/docs` from the published Composer archive either way.

Constructor accepts three shapes: `''` (library defaults), `'/path/to.yml'` (filesystem YAML), `[]` (PHP array, no filesystem I/O). Missing path falls back silently to library defaults — `parisek/styleguide` relies on this when `typography_config` resolves to a not-yet-created project file.

PHP ^8.3. Twig ^3.27 || ^4.0 (forward-compat to Twig 4 alpha; signal-only in CI). Symfony YAML 6/7/8.

## Commands

```bash
composer install
composer check           # primary local code-quality check: cs, test, phpstan — run this before opening a PR
```

CI runs `composer check`'s three parts plus more on top: `composer validate --strict`, the advisory audit, the normalize check, and the full PHP/Twig/Symfony version matrix (see CI matrix below) — a green `composer check` doesn't guarantee green CI, it just clears the fast, deterministic part of it locally.

Granular scripts, in case you need just one:

```bash
composer test            # phpunit
composer phpstan         # static analysis — level 8
composer cs              # php-cs-fixer dry-run (PER-CS)
composer cs:fix          # apply code style
composer normalize       # tidy composer.json
composer normalize:check # composer.json normalization, check-only (what CI runs)
composer audit           # dependency advisory scan
composer validate --strict
```

## CI matrix

The `test` job in `.github/workflows/tests.yml` runs a 3-leg PHP/Twig/Symfony matrix (two more jobs, `composer` hygiene and `cs`, run alongside it — not part of this matrix):

| PHP | Twig | Symfony | Required |
|---|---|---|---|
| 8.3 | ^3.27 | ^7.0 | yes |
| 8.4 | ^3.27 | ^8.0 | yes |
| 8.4 | ^4.0@alpha | ^8.0 | **signal-only** (`continue-on-error: true`) |

The Twig 4 alpha job exists to catch upstream breaks early. Don't promote it to required until Twig 4 stable lands.

PHPStan only runs on the stable jobs (`if: matrix.stable`) — the alpha job's purpose is functional regressions, not type narrowing.

## Per-PR conventions

- **CHANGELOG.md**: every behavior-affecting PR adds an entry under `## [Unreleased]` with [Keep a Changelog](https://keepachangelog.com/) categories.
- **Squash-merge PRs** into `main` so the merge commit subject ends with `(#N)`. `allow_merge_commit`/`allow_rebase_merge` are disabled repo-wide, `squash_merge_commit_title: PR_TITLE` — the PR title mechanically becomes the commit subject on `main`.
- **PR title must be a Conventional Commit** (`commitlint.yml`, `amannn/action-semantic-pull-request`): one of `feat fix docs chore ci test refactor qa`, scope optional/freeform.
- Default branch is `main`, not `master`.

## Release process — DO NOT bypass

Automated by two workflows (mirrors `parisek/timber-kit`). **Never stamp + tag manually** unless the workflow is broken:

1. Trigger **Stamp Release** (Actions tab → `Stamp Release` → Run workflow → enter `X.Y.Z`, no `v` prefix).
2. It validates the version, requires a non-empty `[Unreleased]`, runs `composer test` + `composer phpstan` as guards, stamps `[Unreleased]` → `[X.Y.Z] - DATE` (UTC, leaving a fresh empty `[Unreleased]`), commits `Release X.Y.Z`, tags `vX.Y.Z`, pushes, then dispatches `release.yml`.
3. `release.yml` extracts that tag's CHANGELOG section + the merged-PR list and creates the GitHub Release. Packagist auto-imports the tag (~60s; webhook wired).

`release.yml` also runs on a manual `vX.Y.Z` tag push and via `workflow_dispatch` (re-generate notes for an existing tag).

## PHPStan level

Level 8 (max). The package is small enough that level 8 stays clean without effort. Don't dial down; if a real type-shape problem appears, fix the code, not the level.

`tests/fixtures/*` is excluded (PHPStan would otherwise complain about fixture YAML-as-PHP shenanigans).

## Symfony YAML constraint

`symfony/yaml: ^6.0 || ^7.0 || ^8.0`. The package never deeply integrates with the Symfony container — `Yaml::parse()` is the only call site, in `loadDefaults()`. Widening to a new Symfony major is essentially free if `Yaml::parse()` keeps its signature; verify with a one-off `composer require symfony/yaml:^N` in a scratch checkout before bumping.

`parisek/styleguide` pulls both this package and `parisek/twig-attribute`. Make sure the Symfony YAML constraint here doesn't lag behind that downstream — otherwise the styleguide gets pinned to an older Symfony major than its own constraint would allow.

## Twig 4 forward-compat

The package supports `twig/twig: ^3.27 || ^4.0`. Twig 4's known breaking changes that could matter:
- `Twig\Markup` constructor stricter on `string|null` (we pass `string|\Stringable` and cast; no impact).
- Several deprecated extension hooks removed (we only implement `getFilters()`; no impact).

If Twig 4 stable lands and the alpha job's signal stays clean, drop `@alpha` from the alpha matrix entry and promote it to required.

## Style

- PSR-12, 4-space indent, `declare(strict_types=1)`, `final` by default.
- WHY-not-WHAT comments. Don't reference PRs / call sites in code comments — those rot.
- First-class callable syntax for filters: `$this->applyTypography(...)`, not `[$this, 'applyTypography']` (phpstan-friendly, idiomatic 8.1+).
