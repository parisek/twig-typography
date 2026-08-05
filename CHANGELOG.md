# Changelog

All notable changes to `parisek/twig-typography` are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-08-05

### Changed

- **Rendered output changes.** The package now ships a house typographic
  policy — in its bundled `typography.yml` — and applies it on every render,
  including where a consumer passes a settings path that does not exist.
  Previously that case fell through to php-typography's own defaults. The API
  is unchanged and additive, but review a page before deploying: ampersands
  lose their `<span class="amp">` wrapper, widow protection is off, and
  quotes follow the resolved language.
- **Consuming-project settings files now use the same shape as the bundled
  table.** A project's own settings (a YAML file path or a PHP array, passed
  as `$config`) can carry an optional `languages:` map alongside its global
  keys, keyed by language tag — the same shape the bundled `typography.yml`
  uses for its own thirteen tables. This is what makes a single-language
  override possible: a project can override, say, just Czech's primary quote
  character without touching any other language and without restating the
  rest of Czech's own settings — `languages:` overrides are merged per key,
  not per block, so an entry only needs to state what it actually changes.
- **An unrecognised settings key is now skipped instead of fataling the
  render.** Previously any key without a matching method on
  PHP-Typography's `Settings` class (a typo'd option name, for instance)
  raised `Error: Call to undefined method Settings::...()` and took the whole
  page down. It is now checked against `Settings` via a `set_`-prefix check
  plus `is_callable()` (not `method_exists()`, which also matches
  non-`set_` and non-public members) and silently ignored — no logging, no exception — while every other key in the
  same call still applies. This also closes the path that made the
  `languages:` map itself unsafe to introduce: without this fix, passing it
  through to `Settings` unfiltered would have hit the identical fatal.

### Added

- Per-locale language tables for `cs`, `de`, `de-CH`, `en`, `en-GB`, `fr`,
  `hr`, `hu`, `pl`, `ru`, `sk`, `sl`, `tr`, bundled in `typography.yml`'s
  `languages:` map —
  selected by an optional `$locale_resolver` constructor argument, invoked on
  every filter call so a language switch mid-request is honoured. Dutch and
  Portuguese are deliberately not included yet — Dutch quote practice is
  genuinely mixed rather than settled, and European vs. Brazilian Portuguese
  disagree on the primary pair, so a single table would be wrong for one of
  them. A missing table degrades to the house policy's own language-neutral
  defaults, not a wrong language, so this is a safe omission rather than a
  gap to work around.
- `LocaleResolver` and `SettingsLoader`, both public.

### Fixed

- Czech and German rendered `„…”`, the Polish pair, instead of `„…“`. The
  setting that produced it was the only one available to a consumer, so every
  project carrying it was wrong the same way.
- A consumer with a flat (single-language) settings file had no way to
  override just one language without it applying to every language — the
  settings file predated the per-language table entirely. The `languages:`
  shape above closes this.
- Croatian rendered `„…“`, the Czech/German pair, instead of `„…”`. Fixed
  against pravopis.hr (Institut za hrvatski jezik i jezikoslovlje) rule 8.16;
  nested quotes corrected from a low-9 variant to `‘…’` per rule 8.17.
- `en` mixed American dashes (`set_smart_dashes_style: "traditionalUS"`) with
  British hyphenation (`set_diacritic_language: "en-GB"`). `en` now commits to
  American throughout; a new `en-GB` table carries the British dash
  convention (spaced en-dash) on top of the shared quote/ordinal settings.
- Swiss German had no table of its own and silently fell back to the `de`
  pair (`„…“`), which Swiss orthography does not use. Added `de-CH`
  (`«…»`, nested `‹…›`), per the Bundeskanzlei Schreibweisungen.

### Upgrading from 1.2.x

- Pass a locale resolver to the constructor (`new TypographyExtension('',
  fn () => $currentLocale)`) to get per-language typesetting instead of the
  single hardcoded pair. The callable should return the locale currently
  being rendered, in either `cs_CZ` or `cs` form.
- A project settings file/array passed as the constructor's `$config`
  argument is still read and still applied on top of the bundled language
  table, exactly as before; it is simply optional now rather than required.
  If it only restates what the house policy already sets, it can go. If it
  carries a real override, keep it — and consider moving any single-language
  overrides into a `languages:` sub-map so they stop leaking onto other
  languages.

## [1.2.3] - 2026-06-01

### Security

- Raised the `twig/twig` floor to `^3.27` (alongside `^4.0`) so consumers can't resolve a version affected by [CVE-2026-46634](https://symfony.com/cve-2026-46634) — a sandbox escape via `template_from_string()`, fixed in twig 3.27.

### Changed

- Adopted the shared parisek QA tooling: `composer audit` + `composer normalize` CI gates and a PHP-CS-Fixer (`@PER-CS`) `cs` check (the codebase already matched it). Dev-only; no consumer impact.

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
