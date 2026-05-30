# Changelog

All notable changes to `parisek/twig-typography` are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.2] - 2026-05-30

### Fixed

- Suppress `E_DEPRECATED` around the `mundschenk-at/php-typography` `process()`
  call. The upstream library's latest release (v6.7.0, Nov 2022) predates PHP
  8.4 and uses implicitly-nullable parameter signatures that 8.4+ deprecates;
  with `display_errors` on, those notices were written into the rendered output
  and corrupted the HTML. The suppression is scoped to the single upstream call
  and restores the previous `error_reporting()` level afterwards, so genuine
  errors are unaffected. Stopgap until php-typography ships the type-hint fix
  ([php-typography#189](https://github.com/mundschenk-at/php-typography/pull/189)).

## [1.2.0] - 2026-05-25

### Added

- PHP-array constructor overload: `new TypographyExtension([…])` accepts
  settings directly without going through the filesystem. The existing
  `string $path` overload is unchanged — backwards compatible.
- Test suite (PHPUnit 11/12) covering filter registration, smart-quote
  application, YAML config loading, array config loading, missing-file
  fallback, per-call argument override, and the `$use_defaults` flag.
- CI matrix: PHP 8.3 × Twig 3 × Symfony 7, PHP 8.4 × Twig 3 × Symfony 8,
  plus a signal-only PHP 8.4 × Twig 4 alpha job.
- PHPStan level 8 static analysis (`composer phpstan`).
- Explicit `composer.json` PHP constraint (`^8.3`) — previously the floor
  came transitively from `mundschenk-at/php-typography` and was effectively
  PHP 7.4.
- Symfony 8 support via `symfony/yaml: ^6.0 || ^7.0 || ^8.0`.
- Twig 4 forward-compat via `twig/twig: ^3.0 || ^4.0`.
- `Parisek\Twig\Tests\` autoload mapping for the test suite.
- `CHANGELOG.md` file (this one).

### Changed

- Source moved from repo root to `src/`. PSR-4 map updated; the public
  namespace `Parisek\Twig` and class `TypographyExtension` are unchanged,
  so all `use Parisek\Twig\TypographyExtension;` imports keep working.
- Coding style: strict types declared at the top of every PHP file,
  PSR-12 indentation (4 spaces), explicit type hints on all public method
  signatures (`applyTypography(string $string, array $arguments = [],
  bool $use_defaults = true): string`).
- Private helper renamed `getDefaults()` → `loadDefaults()` — it does
  filesystem I/O, not a property getter.
- Bundled `typography.yml` neutralised to a marker file; the previous
  Czech-leaning defaults moved into the README as a sample config snippet.
  The library's own `Settings(true)` defaults now apply when no
  constructor argument is supplied. Existing consumers that pass their
  own YAML path are unaffected.
- `TwigFilter` callable now uses the PHP 8.1+ first-class callable syntax
  (`$this->applyTypography(...)`) instead of the legacy
  `[$this, 'applyTypography']` array form — phpstan-friendly and
  idiomatic.

### Removed

- Dropped Twig 2 support (`twig/twig: ^2.4`). Twig 2 reached end-of-life
  in November 2022.
- Dropped Symfony 5 support (`symfony/yaml: ^5.0`). Symfony 5 reached
  end-of-life in November 2024.
- `{% typography %}` block-tag documentation removed from README. The
  block tag was never implemented in this package's code — only the
  filter form (`|typography`) ever worked. The README claim dated from
  the project's initial commit (2021).

### Fixed

- README's Usage snippet uses the Twig 3+ API (`new \Twig\Environment(…)`)
  instead of the long-removed Twig 1.x/2.x `Twig_Environment(…)` form.

## [1.1.0] - 2024-10-10

### Added

- Symfony 7 support (`symfony/yaml: ^5.0 || ^6.0 || ^7.0`).

## [1.0.0]

Initial stable release. See repo git history for early development
notes; no `CHANGELOG.md` existed before 1.2.0.
