# parisek/twig-typography 1.2.0 reshape — Design

**Status:** approved, ready for implementation plan.
**Created:** 2026-05-25.
**Predecessor:** `docs/handoffs/2026-05-25-twig-typography-reshape.md` in
`parisek/styleguide` (the handoff that triggered this brainstorm).
**Successor:** implementation plan via `superpowers:writing-plans`.

## Goal

Modernise `parisek/twig-typography` to 2026 standards — strict types,
test suite, current Symfony / Twig / PHP constraints — without breaking
any of the 45+ consumer usages across the parisek stack. Ship as a
drop-in **minor release `1.2.0`**: no `2.0` major bump, no PR into
downstream projects (`parisek/styleguide`, `tailwind-base`, `pm-a`).

## Background

The package is alive but stagnant. It exposes one Twig filter
(`|typography`) backed by `mundschenk-at/php-typography`, has 83 LOC of
PHP plus a 7-line YAML defaults file, no tests, and a CI workflow with
the test step commented out. Latest release v1.1.0 (October 2024) was a
Symfony 7 bump; before that, an 18-month silence.

The package's surface — class `Parisek\Twig\TypographyExtension`,
constructor taking a YAML file path, filter `|typography` — is used
heavily and predictably. Consumers always supply a project-tuned cs-CZ
YAML; the bundled defaults are effectively dead code that the
constructor's `file_exists()` check has been routing around since 2021.

The README has advertised a `{% typography %}` Twig block tag since
2021. Code has never implemented it. Zero consumer templates use it.

## Decisions made

| # | Decision | Notes |
|---|---|---|
| 1 | Drop-in BC; ship as `1.2.0` | No 2.0, no PR into consumers. PHP-floor bump is safe — Composer holds old projects on 1.1.x. |
| 2 | PHP `^8.3` | Matches `parisek/styleguide`. Unblocks future Symfony 8 (PHP ≥8.4) via Composer resolve. |
| 3 | 8 smoke/behavior PHPUnit tests | Filter contract, plain pass-through, smart-quotes, YAML config, array config, missing-file fallback, per-call overrides, `$use_defaults` flag. |
| 4 | Bundled `typography.yml` neutralised | Empty marker file; library defaults apply; cs sample moves to README. |
| 5 | Constructor `string\|array $config` overload | Additive. File-path call sites unchanged. Array form for filesystem-free Symfony/micro-framework consumers. |
| 6 | Twig 3 + 4 forward-compat constraint | `twig/twig: ^3.0 \|\| ^4.0`. Twig 4 alpha runs as signal-only CI job. |

## Non-goals

- No `{% typography %}` token parser (despite README's 4-year-old
  claim — to be deleted from README).
- No PSR-3 logger for malformed YAML (silent fallback to library
  defaults is the current contract; preserved as-is).
- No PHP-typography feature surface expansion (new settings, new
  Twig functions). Out of scope.
- No Symfony bundle / Drupal module wrapper.
- No rename, no namespace change, no filter rename.

## Section 1 — Architecture & file layout

### Repo today

```
twig-typography/
├── TypographyExtension.php   # 83 LOC, namespace Parisek\Twig, autoload "Parisek\\Twig\\": ""
├── typography.yml            # 7 LOC bundled defaults
├── composer.json
├── README.md
├── LICENSE.txt
└── .github/workflows/php.yml # tests step commented out since inception
```

### Repo after reshape

```
twig-typography/
├── src/
│   └── TypographyExtension.php   # ~100 LOC, PSR-12, strict types, typed
├── typography.yml                 # empty marker file, sample lives in README
├── tests/
│   ├── TypographyExtensionTest.php
│   └── fixtures/
│       └── cs.yml
├── composer.json                  # php ^8.3, twig ^3||^4, sym/yaml ^6||^7||^8
├── phpstan.neon                   # level 8
├── phpunit.xml.dist
├── README.md                      # rewritten
├── CHANGELOG.md                   # new, Keep-a-Changelog format
├── LICENSE.txt                    # unchanged
└── .github/workflows/ci.yml       # renamed from php.yml, 3-job matrix
```

### Architectural choices

- **`src/` directory.** PSR-4 changes from `"Parisek\\Twig\\": ""` to
  `"Parisek\\Twig\\": "src/"`. The public namespace `Parisek\Twig` is
  unchanged; only the on-disk path moves. Every `use
  Parisek\Twig\TypographyExtension;` import keeps working.
- **Single public class.** No internal decomposition into
  `Settings` / `Engine` / `Filter` — the file is ~100 LOC and SRP-clean
  (one Twig extension wrapping one filter that delegates to one engine).
- **No TokenParser.** The fictional `{% typography %}` block tag stays
  fictional; the README claim is removed.
- **`is_safe: ['html']`** is preserved on the filter. The engine emits
  `<sup>`, `<span class="…">`, etc.; templates must not escape it.

## Section 2 — Public API contract

### Class signature

```php
final class Parisek\Twig\TypographyExtension extends \Twig\Extension\AbstractExtension
```

- `final` (already final in 1.1.x — preserved).
- Same namespace, same class name, same parent.

### Constructor

```php
public function __construct(string|array $config = '') { ... }
```

- `string ''` (default) → use the bundled `typography.yml` alongside the
  source file. Since 1.2.0 ships an empty marker, this resolves to
  library defaults.
- `string '/path/to/foo.yml'` → load and apply settings from the file.
  This is the existing 1.1.x behaviour for the same call shape.
- `string '/path/nonexistent.yml'` → file doesn't exist → silent
  fallback to library defaults. Preserves the `file_exists()` check
  contract.
- `array [...]` → use as settings directly, no filesystem access. New
  in 1.2.0.
- `array []` → library defaults (same as empty-string default).

### Public methods

```php
public function getFilters(): array
public function applyTypography(string $string, array $arguments = [], bool $use_defaults = true): string
```

Changes vs. 1.1.x:

- `applyTypography` gains full type hints and return type. Twig calls
  the filter without `strict_types=1`; coercion at the caller boundary
  remains loose. Drop-in BC for templates.
- `getFilters` gains `: array` return type.

### Filter

```
name:    typography
target:  $this->applyTypography
options: ['is_safe' => ['html']]
```

Unchanged from 1.1.x.

### Private method rename

`getDefaults()` → `loadDefaults()`. The method does filesystem I/O; the
`get*` prefix in PHP convention suggests a property accessor. Private,
so safe to rename.

## Section 3 — Composer constraints & dependencies

### `composer.json` diff

```diff
 {
     "name": "parisek/twig-typography",
     "description": "A Twig extension with typography filter",
     "keywords": ["twig", "typography"],
     "homepage": "https://github.com/parisek/twig-typography",
     "license": "GPL-2.0-or-later",
     "require": {
+        "php": "^8.3",
         "mundschenk-at/php-typography": "^6.0",
-        "symfony/yaml": "^5.0 || ^6.0 || ^7.0",
-        "twig/twig": "^2.4 || ^3.0"
+        "symfony/yaml": "^6.0 || ^7.0 || ^8.0",
+        "twig/twig": "^3.0 || ^4.0"
+    },
+    "require-dev": {
+        "phpunit/phpunit": "^11.0 || ^12.0",
+        "phpstan/phpstan": "^2.0"
     },
     "autoload": {
         "psr-4": {
-            "Parisek\\Twig\\": ""
+            "Parisek\\Twig\\": "src/"
         }
+    },
+    "autoload-dev": {
+        "psr-4": {
+            "Parisek\\Twig\\Tests\\": "tests/"
+        }
+    },
+    "scripts": {
+        "test": "vendor/bin/phpunit",
+        "phpstan": "vendor/bin/phpstan analyse"
+    },
+    "config": {
+        "platform": {
+            "php": "8.3"
+        }
+    },
+    "minimum-stability": "stable",
+    "prefer-stable": true
 }
```

### Rationale

| Change | Why |
|---|---|
| `php: ^8.3` added | 1.1.x had no PHP constraint at all — floor came transitively from `php-typography` (PHP 7.4). Explicit floor aligns with `parisek/styleguide`. |
| `symfony/yaml`: drop `^5.0`, add `^8.0` | Symfony 5 reached EOL Nov 2024. Symfony 8 (requires PHP ≥8.4.1) added for forward-compat — Composer picks the right major per runtime. |
| `twig/twig`: drop `^2.4`, add `^4.0` | Twig 2 reached EOL Nov 2022. Twig 4 alpha is published; our surface (`AbstractExtension`, `TwigFilter`) is stable across 3→4. CI signal-only job validates. |
| `phpunit ^11.0 \|\| ^12.0` | Matches `parisek/styleguide`. Holds PHP 8.3 floor. |
| `phpstan ^2.0` | Matches `parisek/styleguide`. Level 8 in `phpstan.neon`. |
| `platform.php: "8.3"` | Locks local/CI resolve at the floor. We test the worst case, not the best. |
| `minimum-stability: stable` + `prefer-stable: true` | Explicit defaults. Combined with `^4.0` in twig constraint, alpha Twig 4 won't be picked automatically — Composer prefers Twig 3 stable until Twig 4 ships stable. |
| Autoload split (`src/` + `tests/`) | Tests excluded from production autoload map. Standard PSR-4 separation. |

### Symfony surface used in code

Only `Symfony\Component\Yaml\Yaml::parse()`. No BC breaks across Symfony
6 → 7 → 8 for that call. The 8.0 changelog entry about removing duplicate
null mapping keys doesn't affect typography YAML files.

## Section 4 — Test suite

### Framework

PHPUnit 11/12, PSR-4 namespace `Parisek\Twig\Tests\`, PHP 8 attributes
(`#[Test]`) instead of doc-tags. Style mirrors
`parisek/styleguide`'s test layout for consistency across the parisek
stack.

### Test file

`tests/TypographyExtensionTest.php` — single class. Eight tests in
~100 LOC is below the threshold where splitting helps.

### Tests

1. **`filter_is_registered_with_html_safety`** — `getFilters()` returns
   one `TwigFilter` named `typography` with
   `options['is_safe'] === ['html']`. Locks the contract that templates
   depend on (without `is_safe`, downstream templates would suddenly
   emit literal `&lt;sup&gt;` tags after an upgrade).

2. **`plain_ascii_string_passes_through_unchanged_when_no_settings`** —
   `applyTypography('Hello world.', [], true)` returns
   `'Hello world.'`. Sanity check that the engine doesn't mangle inputs
   it shouldn't touch.

3. **`smart_quotes_replace_cs_style_low9_quotes`** — Constructor with
   cs config `['set_smart_quotes' => true, 'set_smart_quotes_primary' =>
   'doubleLow9']`; filter on `'He said "hello".'` produces output
   containing `„` and `"`. Hot-path real-life behaviour; catches
   upstream regressions in `php-typography` cs handling.

4. **`yaml_config_path_is_loaded_and_applied`** — Constructor with
   `__DIR__ . '/fixtures/cs.yml'`. Settings from the YAML drive the
   smart-quote replacement (asserted via output diff). Validates the
   file-path overload path.

5. **`array_config_is_applied_without_filesystem`** — Constructor with
   `['set_smart_quotes' => true]`. Identical output to a YAML carrying
   the same setting. Validates the new `string|array` overload's array
   branch.

6. **`missing_yaml_path_falls_back_silently_to_library_defaults`** —
   Constructor with `/path/does/not/exist.yml`. Filter still works,
   throws no exception, returns library-default-processed string.
   Preserves the silent-fallback contract from 1.1.x.

7. **`per_call_arguments_override_constructor_defaults`** — Constructor
   with `['set_smart_quotes' => true]`; filter call
   `applyTypography('"x"', ['set_smart_quotes' => false])`. Output has
   straight quotes. Validates that the third filter argument merges
   *over* defaults (documented behaviour, never tested).

8. **`use_defaults_false_skips_library_defaults`** —
   `applyTypography('"x"', [], false)`. Output has straight quotes
   because no settings get initialised at all. Exercises the
   `$use_defaults` flag.

### Fixtures

```
tests/fixtures/
└── cs.yml      # 3-4 keys: set_smart_quotes, set_smart_quotes_primary, set_diacritic_language
```

No `invalid.yml`. `Yaml::parse()` on malformed YAML throws
`ParseException`, which the current code doesn't catch. Codifying
that as a test means either changing the contract (out of scope) or
asserting on an edge case the code never handled deliberately.

### What's not tested

- Snapshot/golden-file checks against long documents — brittle, couples
  test outcomes to upstream `php-typography` releases.
- Mock of the PHP_Typography engine — integration against the real
  engine is simpler and the engine is deterministic.
- Performance benchmarks — the wrapper is ~50 LOC; performance is
  bounded by upstream.

## Section 5 — CI workflow

### File

`.github/workflows/ci.yml` (renamed from `php.yml`). Neutral name
reflecting that the workflow runs tests *and* static analysis.

### Content

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

permissions:
  contents: read

jobs:
  test:
    name: PHP ${{ matrix.php }} / Twig ${{ matrix.twig }} / Symfony ${{ matrix.symfony }}
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        include:
          - { php: '8.3', twig: '^3.0',        symfony: '^7.0', stable: true  }
          - { php: '8.4', twig: '^3.0',        symfony: '^8.0', stable: true  }
          - { php: '8.4', twig: '^4.0@alpha',  symfony: '^8.0', stable: false }
    continue-on-error: ${{ !matrix.stable }}

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - name: Pin matrix deps
        run: |
          composer require --no-update --dev \
            "twig/twig:${{ matrix.twig }}" \
            "symfony/yaml:${{ matrix.symfony }}"

      - name: Install dependencies
        run: composer update --prefer-dist --no-progress

      - name: Run tests
        run: composer test

      - name: Static analysis
        if: matrix.stable
        run: composer phpstan
```

### Matrix design

Three `include` entries instead of a Cartesian product:

| PHP | Twig | Symfony | Stable | Why |
|---|---|---|---|---|
| 8.3 | `^3.0` | `^7.0` | ✅ | Floor case — `parisek/styleguide`'s current runtime. |
| 8.4 | `^3.0` | `^8.0` | ✅ | Symfony 8 path — Symfony 8 requires PHP ≥8.4.1. |
| 8.4 | `^4.0@alpha` | `^8.0` | ❌ signal-only | Forward-compat probe for Twig 4. `continue-on-error` until Twig 4 ships stable. |

PHP 8.3 × Symfony 8 is excluded — that combination can't resolve
(Symfony 8 requires PHP ≥8.4.1).
PHP 8.4 × Symfony 7 is excluded — Symfony 7 is already covered by
PHP 8.3.

### Design choices

- **`fail-fast: false`** — when Twig 4 alpha breaks, I still want to
  see stable jobs' results.
- **`continue-on-error: ${{ !matrix.stable }}`** — Twig 4 alpha job
  gives signal but doesn't block merges. When Twig 4 ships stable, flip
  `stable: true` in one commit.
- **PHPStan on stable rows only** — Twig 4 alpha can emit new type
  declarations PHPStan doesn't recognise yet, producing false
  failures on a contract that's identical between Twig 3 and Twig 4
  stable.
- **`coverage: none`** — coverage measurement slows tests 3-5×. For 8
  deterministic smoke tests, no value in the main CI loop. Available
  ad-hoc via `composer test -- --coverage-text`.
- **`composer require --no-update`** — the trick that lets one
  `composer.json` serve all three matrix jobs. Rewrites constraints
  locally, then `composer update` resolves with the override. Standard
  pattern for multi-version CI.

### Considered and rejected

- `composer audit` step — noise from upstream advisories outside our
  control. Belongs on a release schedule, not on every push.
- PHP 8.5 matrix row — 8.5 ships Nov 2025; the parisek stack hasn't
  adopted yet. Add when consumers do.
- `--prefer-lowest` job — for a library with a narrow Symfony surface
  (`Yaml::parse()` only), floor-resolution regression risk is minimal
  and doesn't justify the CI complexity.

## Section 6 — Documentation

### README (full rewrite)

The current README has four problems:

1. **Fictional `{% typography %}` block tag** documentation
   (lines 51-58 in 1.1.x). Feature was never implemented. Delete.
2. **`Twig_Environment($loader)` snippet** — Twig 1.x/2.x API; in
   Twig 3+ it's `new \Twig\Environment(…)`. Update.
3. **No cs config sample** — all real consumers carry rich cs-CZ YAML
   but a new adopter sees nothing about how to write one.
4. **No version-requirement summary** — adopters must read
   `composer.json` to learn PHP/Twig/Symfony floors.

New README structure (~85 LOC):

```markdown
# Twig Typography Extension

Twig adapter for [PHP-Typography](https://github.com/mundschenk-at/php-typography) —
smart quotes, dashes, ellipses, hyphenation, widow protection, fraction
glyphs, ordinal suffixes, math symbols, CSS hooks for styling.

## Requirements
- PHP 8.3+
- Twig 3 or 4
- Symfony YAML 6, 7, or 8 (only when loading config from a `.yml` file)

## Installation
\```bash
composer require parisek/twig-typography
\```

## Usage

### Register on a Twig environment
[3 examples: bare, file path, PHP array]

### In templates
[filter, with per-call args]

## Configuration
[Settings reference link + cs-CZ sample]

## What's not included
[Explicit denial of {% typography %} block tag, pointer to macro
workaround]

## License
GPL-2.0-or-later, see `LICENSE.txt`.
```

The cs-CZ sample lifted from `tailwind-base`'s production
`typography.yml` (clearly labelled as a copy-and-tune sample, not a
recommended default).

### CHANGELOG.md (new file)

Keep-a-Changelog format. SemVer adherence noted up top. Initial
1.2.0 entry sections (Added / Changed / Removed / Fixed) covering every
diff from this spec — see section 6 of the brainstorming transcript for
the exact draft.

The CHANGELOG is intentionally added in 1.2.0 itself (not retroactively
backfilled for 1.0.0 → 1.1.0). The release notes for those versions live
on GitHub releases and in commit messages; backfilling them would
require archaeology that delivers no current value.

## Section 7 — Migration path & release

### Drop-in BC means no consumer PR

The package's public surface — class, namespace, constructor signature,
filter name — is unchanged. Adding the array overload is purely
additive (`string` calls still work). The PHP-floor bump from PHP 7.4
(transitive) to PHP 8.3 (explicit) is safe in Composer's resolver:
projects on older PHP are held at 1.1.0 by `composer update`'s normal
resolution, not broken.

### Release sequence

1. Branch `feature/1.2.0-modernization` in `/Users/pari/Sites/twig-typography`.
2. Implementation per the writing-plans output. All commits land on the branch.
3. PR `main ← feature/1.2.0-modernization` on GitHub. Self-review (no
   second reviewer available for parisek/* libraries — code-reviewer
   subagent during implementation suffices).
4. Squash-merge to `main` once CI matrix is green.
5. `git tag v1.2.0 && git push --tags`.
6. Packagist webhook auto-imports the tag; `packagist.org/packages/parisek/twig-typography`
   shows v1.2.0 as latest stable within minutes.

No `composer styleguide:remote`-style switch is needed for this repo —
it has no local-vs-remote path-repo setup. Tag push is enough.

### What consumers see (no action required)

| Project | Constraint | Resolves to after release |
|---|---|---|
| `parisek/styleguide` | `"parisek/twig-typography": "^1.0"` (direct require) | v1.2.0 ✓ |
| `tailwind-base` | transitive via styleguide | v1.2.0 ✓ |
| `pm-a` | transitive via styleguide | v1.2.0 ✓ |

### Post-release verification

Smoke-check each consumer (~30 seconds total):

```bash
# styleguide (direct consumer):
cd /Users/pari/Sites/styleguide && composer update parisek/twig-typography && composer test
# expect: 99 tests pass

# tailwind-base + pm-a (transitive consumers):
# open any page using |typography (article-full, etc.) in browser,
# confirm smart-quotes/dashes still render correctly
```

### Rollback

If anything breaks: `git tag -d v1.2.0`, `git push --delete origin v1.2.0`,
let Packagist re-sync (or trigger update via webhook). Consumers on
`composer update` resolution will fall back to v1.1.0. Reversible.

### Optional `parisek/styleguide` CHANGELOG note

Optional `[Unreleased]` entry in the styleguide changelog noting the
transitive bump. Strictly not required — drop-in updates don't usually
warrant an entry in *our* changelog (which describes *our* changes) —
but a brief note serves future-archaeology purposes:

```markdown
### Changed

- `parisek/twig-typography` bumped 1.1.x → 1.2.0 (transitive). 1.2.0 is the
  modernization release — strict types, PHPUnit + PHPStan test suite,
  Symfony 8 + Twig 4 forward-compat, PHP 8.3 floor. No code changes
  required in styleguide; public API is unchanged.
```

Decision left open — include or skip per release-time judgement.

## Acceptance criteria

A pull request implementing this spec lands when:

1. All eight tests in section 4 pass on every stable matrix row.
2. PHPStan level 8 reports zero errors on stable rows.
3. `composer validate --strict` exits 0.
4. README matches the structure in section 6 (rewrite, not just edits).
5. CHANGELOG carries the 1.2.0 entry with every diff from this spec
   reflected.
6. `Parisek\Twig\TypographyExtension` class is namespace- and
   signature-compatible with 1.1.x for the constructor and `getFilters`.
7. The constructor accepts `string|array $config = ''`; both branches
   covered by tests (sections 4.4 and 4.5).
8. `static/index.php` in `parisek/styleguide` works unchanged against
   v1.2.0 (post-release smoke check).
