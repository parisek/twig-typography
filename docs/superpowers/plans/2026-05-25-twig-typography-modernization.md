# parisek/twig-typography 1.2.0 Modernization — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize `parisek/twig-typography` to 2026 standards (strict types, PHPUnit + PHPStan suite, current Symfony/Twig/PHP constraints, additive `string|array` constructor overload) while preserving drop-in BC for every existing consumer.

**Architecture:** Single Twig extension class (`Parisek\Twig\TypographyExtension`) wrapping `mundschenk-at/php-typography`. Move source from repo root into `src/`, add `tests/`, replace the test-less CI workflow with a 3-job matrix (PHP 8.3/8.4 × Twig 3/4 × Symfony 7/8). Public namespace + class + filter name stay identical so 45+ downstream callsites need no changes.

**Tech Stack:** PHP 8.3, Twig 3 (+ 4 forward-compat), Symfony YAML 6/7/8, PHPUnit 11/12, PHPStan 2 (level 8), GitHub Actions, `mundschenk-at/php-typography` v6.

**Spec:** `docs/superpowers/specs/2026-05-25-twig-typography-reshape-design.md`. Read it before starting — this plan implements every section.

**Working directory:** `/Users/pari/Sites/twig-typography`. Already on branch `feature/1.2.0-modernization` with the spec doc committed (`302e050`).

---

## File map

Files this plan touches (and the task that owns each):

| File | Action | Owner task |
|---|---|---|
| `composer.json` | Modify | 1 |
| `src/TypographyExtension.php` | Create (move from `TypographyExtension.php`) | 1 |
| `TypographyExtension.php` | Delete (after move) | 1 |
| `phpunit.xml.dist` | Create | 1 |
| `phpstan.neon` | Create | 1 |
| `.gitignore` | Create (or extend) | 1 |
| `src/TypographyExtension.php` | Modify (modernize) | 2 |
| `tests/fixtures/cs.yml` | Create | 3 |
| `tests/TypographyExtensionTest.php` | Create + extend | 3, 4, 5 |
| `typography.yml` | Modify (neutralize) | 6 |
| `README.md` | Rewrite | 7 |
| `CHANGELOG.md` | Create | 8 |
| `.github/workflows/php.yml` | Delete | 9 |
| `.github/workflows/ci.yml` | Create | 9 |

---

## Task 1: Restructure repo + modernize composer.json

**Files:**
- Create: `src/TypographyExtension.php` (verbatim copy of root file — modernization happens in Task 2)
- Delete: `TypographyExtension.php` (after move)
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `phpstan.neon`
- Create: `.gitignore`

- [ ] **Step 1: Move PHP source into `src/`**

```bash
cd /Users/pari/Sites/twig-typography
mkdir -p src
git mv TypographyExtension.php src/TypographyExtension.php
```

Verify: `ls src/` shows `TypographyExtension.php`; `ls TypographyExtension.php` errors with "No such file or directory".

- [ ] **Step 2: Rewrite `composer.json`**

Replace the entire file content with:

```json
{
    "name": "parisek/twig-typography",
    "description": "A Twig extension with typography filter",
    "keywords": ["twig", "typography"],
    "homepage": "https://github.com/parisek/twig-typography",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": "^8.3",
        "mundschenk-at/php-typography": "^6.0",
        "symfony/yaml": "^6.0 || ^7.0 || ^8.0",
        "twig/twig": "^3.0 || ^4.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0 || ^12.0",
        "phpstan/phpstan": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Parisek\\Twig\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Parisek\\Twig\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "vendor/bin/phpunit",
        "phpstan": "vendor/bin/phpstan analyse"
    },
    "config": {
        "platform": {
            "php": "8.3"
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 3: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 4: Create `phpstan.neon`**

```yaml
parameters:
    level: 8
    paths:
        - src
        - tests
    excludePaths:
        - tests/fixtures/*
```

- [ ] **Step 5: Create `.gitignore`**

```
/vendor/
/composer.lock
/.phpunit.cache/
/.phpstan-cache/
.DS_Store
```

(`composer.lock` is excluded because this is a library; consumers determine resolution.)

- [ ] **Step 6: Validate composer.json and install dev deps**

```bash
cd /Users/pari/Sites/twig-typography
composer validate --strict
```

Expected output: `./composer.json is valid` (no warnings on `strict`).

```bash
composer install --no-progress
```

Expected: installs `phpunit/phpunit`, `phpstan/phpstan`, `mundschenk-at/php-typography`, `symfony/yaml`, `twig/twig`, plus transitive deps. No errors.

- [ ] **Step 7: Sanity check that autoload resolves the moved class**

```bash
php -r 'require "vendor/autoload.php"; var_dump(class_exists(\Parisek\Twig\TypographyExtension::class));'
```

Expected: `bool(true)`.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "$(cat <<'EOF'
chore: restructure repo into src/ and modernize composer.json

- Move TypographyExtension.php into src/ — PSR-4 namespace unchanged.
- Add explicit PHP 8.3 floor; drop Symfony 5 + Twig 2 ranges; add
  Symfony 8 + Twig 4 forward-compat.
- Add require-dev (phpunit, phpstan), composer scripts (test, phpstan),
  platform pin, stable preference.
- Add phpunit.xml.dist, phpstan.neon, .gitignore.

No source changes yet — modernization in next commit.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Modernize PHP source (strict types, type hints, array overload)

**Files:**
- Modify: `src/TypographyExtension.php`

Replace the entire file with the modernized version below.

- [ ] **Step 1: Rewrite `src/TypographyExtension.php`**

```php
<?php

declare(strict_types=1);

namespace Parisek\Twig;

use PHP_Typography\PHP_Typography;
use PHP_Typography\Settings;
use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TypographyExtension extends AbstractExtension
{
    /**
     * Either a YAML file path (string) or an associative settings array.
     * Resolved into a flat settings array via {@see loadDefaults()} once
     * per filter invocation.
     *
     * @var string|array<string, mixed>
     */
    private string|array $config;

    /**
     * @param string|array<string, mixed> $config
     *   - string ''       → use the bundled marker `typography.yml` (library defaults).
     *   - string '/path'  → load the YAML file at the given absolute path.
     *   - array  []|[...] → use the array as settings directly; no filesystem I/O.
     *
     * A non-existent file path falls back silently to library defaults, preserving
     * the 1.1.x behaviour that consumers (notably parisek/styleguide) rely on
     * when their `typography_config` key resolves to a path the project hasn't
     * created yet.
     */
    public function __construct(string|array $config = '')
    {
        $this->config = $config;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'typography',
                $this->applyTypography(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * Apply PHP-Typography to a string.
     *
     * @param array<string, mixed> $arguments  Per-call setting overrides; merged on top of constructor defaults.
     * @param bool                 $use_defaults  Initialise PHP-Typography's own sane defaults before applying ours.
     */
    public function applyTypography(string $string, array $arguments = [], bool $use_defaults = true): string
    {
        $settings = new Settings($use_defaults);

        $merged = array_merge($this->loadDefaults(), $arguments);
        foreach ($merged as $setting => $value) {
            $settings->{$setting}($value);
        }

        return (new PHP_Typography())->process($string, $settings);
    }

    /**
     * Resolve the constructor argument into a flat settings array.
     *
     * @return array<string, mixed>
     */
    private function loadDefaults(): array
    {
        if (is_array($this->config)) {
            return $this->config;
        }

        $path = $this->config !== '' ? $this->config : __DIR__ . '/../typography.yml';

        if (!is_file($path)) {
            return [];
        }

        $parsed = Yaml::parse((string) file_get_contents($path));

        return is_array($parsed) ? $parsed : [];
    }
}
```

Key changes from 1.1.x:

1. **`declare(strict_types=1)`** at top.
2. **PSR-12 indentation** (4 spaces).
3. **Constructor signature** widens to `string|array $config`. Old default `''` preserved.
4. **`getFilters()` returns `array`** with explicit type.
5. **`applyTypography()` fully typed** — `string $string, array $arguments = [], bool $use_defaults = true): string`.
6. **`TwigFilter` callable** is `$this->applyTypography(...)` (first-class callable syntax, PHP 8.1+) instead of `[$this, 'applyTypography']` — phpstan-friendly and idiomatic 8.3.
7. **`getDefaults()` renamed to `loadDefaults()`** — it does I/O, not property access.
8. **`loadDefaults()` handles both string and array branches.** Array branch returns as-is. String branch resolves '' → bundled `typography.yml` next to `src/`, falls back to `[]` on missing file or non-array parse result.
9. **`is_file()` instead of `file_exists()`** — narrower (true only for regular files, not directories), matches phpstan's recommendation.
10. **Default file resolution** goes via `__DIR__ . '/../typography.yml'` because the source is now in `src/` while the bundled YAML stays at the repo root.

- [ ] **Step 2: Verify autoload still resolves**

```bash
cd /Users/pari/Sites/twig-typography
php -r 'require "vendor/autoload.php"; new \Parisek\Twig\TypographyExtension(); echo "ok\n";'
```

Expected output: `ok`.

- [ ] **Step 3: Run PHPStan against the modernized source**

```bash
composer phpstan
```

Expected: `[OK] No errors`.

If errors appear, fix them inline before committing — the most likely issues are:
- Missing `@param` doc-comments for array types (PHPStan level 8 requires array shape hints) → add them as shown.
- `array_merge` argument types — already handled because `loadDefaults()` returns `array<string, mixed>`.

- [ ] **Step 4: Commit**

```bash
git add src/TypographyExtension.php
git commit -m "$(cat <<'EOF'
refactor: modernize TypographyExtension to PHP 8.3 standards

- Add declare(strict_types=1) and full type hints on public methods.
- Constructor accepts string|array $config (array form is new — file-path
  form unchanged for BC).
- Rename private getDefaults() → loadDefaults() — it does I/O.
- Use first-class callable syntax for TwigFilter target.
- PSR-12 indentation (4 spaces).
- Resolve bundled typography.yml relative to the new src/ location.

Public API (namespace, class, constructor first-string-arg behaviour,
filter name, is_safe flag) unchanged — drop-in BC for all consumers.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Test infrastructure + first 3 tests (registration, plain pass-through, smart quotes)

**Files:**
- Create: `tests/fixtures/cs.yml`
- Create: `tests/TypographyExtensionTest.php`

- [ ] **Step 1: Create `tests/fixtures/cs.yml`**

```yaml
# Test fixture — Czech smart-quote configuration used by
# TypographyExtensionTest::yaml_config_path_is_loaded_and_applied.
set_diacritic_language: "cs"
set_smart_quotes: true
set_smart_quotes_primary: "doubleLow9"
```

- [ ] **Step 2: Create `tests/TypographyExtensionTest.php` with the first three tests**

```php
<?php

declare(strict_types=1);

namespace Parisek\Twig\Tests;

use Parisek\Twig\TypographyExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

final class TypographyExtensionTest extends TestCase
{
    #[Test]
    public function filter_is_registered_with_html_safety(): void
    {
        $filters = (new TypographyExtension())->getFilters();

        self::assertCount(1, $filters);
        self::assertInstanceOf(TwigFilter::class, $filters[0]);
        self::assertSame('typography', $filters[0]->getName());
        self::assertSame(['html'], $filters[0]->getSafe(new \Twig\Node\Node()));
    }

    #[Test]
    public function plain_ascii_string_passes_through_unchanged_when_no_settings(): void
    {
        $extension = new TypographyExtension([]);

        self::assertSame('Hello world.', $extension->applyTypography('Hello world.'));
    }

    #[Test]
    public function smart_quotes_replace_cs_style_low9_quotes(): void
    {
        $extension = new TypographyExtension([
            'set_smart_quotes' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        $result = $extension->applyTypography('He said "hello".');

        self::assertStringContainsString('„', $result);
        self::assertStringContainsString('"', $result);
        self::assertStringNotContainsString('"hello"', $result);
    }
}
```

- [ ] **Step 3: Run the tests**

```bash
cd /Users/pari/Sites/twig-typography
composer test
```

Expected: `OK (3 tests, ...)` — all three green.

If `filter_is_registered_with_html_safety` fails on the `getSafe()` line: Twig's `TwigFilter::getSafe()` API takes a `Node` instance because the safety contract can depend on the node being filtered. Passing an empty `Node` works for this filter (it returns the static `is_safe` config). If the test still fails, inspect `TwigFilter::getSafe()` signature in `vendor/twig/twig/src/TwigFilter.php` and adjust.

- [ ] **Step 4: Run PHPStan**

```bash
composer phpstan
```

Expected: `[OK] No errors`.

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "$(cat <<'EOF'
test: filter registration + plain pass-through + cs smart quotes

First three of eight planned tests. Establishes the TypographyExtensionTest
class, sets up the cs.yml fixture, and locks the three highest-priority
contracts: the filter exists with is_safe: html, plain ASCII strings are
not mangled, and the cs-style smart-quote path produces the expected
„low9" replacement.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Two more tests (YAML path loading, array config without filesystem)

**Files:**
- Modify: `tests/TypographyExtensionTest.php`

- [ ] **Step 1: Append two test methods inside the test class**

Add these methods after `smart_quotes_replace_cs_style_low9_quotes`:

```php
    #[Test]
    public function yaml_config_path_is_loaded_and_applied(): void
    {
        $extension = new TypographyExtension(__DIR__ . '/fixtures/cs.yml');

        $result = $extension->applyTypography('He said "hello".');

        self::assertStringContainsString('„', $result);
        self::assertStringContainsString('"', $result);
    }

    #[Test]
    public function array_config_is_applied_without_filesystem(): void
    {
        $arrayExt = new TypographyExtension([
            'set_smart_quotes' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);
        $yamlExt = new TypographyExtension(__DIR__ . '/fixtures/cs.yml');

        $input = 'She said "hi".';

        self::assertSame(
            $yamlExt->applyTypography($input),
            $arrayExt->applyTypography($input),
            'array config should produce the same output as the equivalent YAML config',
        );
    }
```

- [ ] **Step 2: Run the tests**

```bash
composer test
```

Expected: `OK (5 tests, ...)`.

The `array_config_is_applied_without_filesystem` test deliberately uses a fixture YAML that mirrors the array — if `loadDefaults()` mis-handles either branch, the equality assertion catches the divergence.

- [ ] **Step 3: Commit**

```bash
git add tests/TypographyExtensionTest.php
git commit -m "$(cat <<'EOF'
test: yaml path + array config produce identical output

Two more contract tests: the file-path constructor branch correctly
parses the cs.yml fixture and applies settings; the new array branch
produces output identical to the equivalent YAML for the same settings.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Final three tests (missing-file fallback, per-call override, use_defaults flag)

**Files:**
- Modify: `tests/TypographyExtensionTest.php`

- [ ] **Step 1: Append the final three test methods**

Add after `array_config_is_applied_without_filesystem`:

```php
    #[Test]
    public function missing_yaml_path_falls_back_silently_to_library_defaults(): void
    {
        $extension = new TypographyExtension('/path/that/does/not/exist.yml');

        // No exception; filter returns something sensible (engine default behaviour
        // — which for "hello world." is the input unchanged).
        self::assertSame('hello world.', $extension->applyTypography('hello world.'));
    }

    #[Test]
    public function per_call_arguments_override_constructor_defaults(): void
    {
        $extension = new TypographyExtension([
            'set_smart_quotes' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        $defaultResult = $extension->applyTypography('"x"');
        $overriddenResult = $extension->applyTypography('"x"', ['set_smart_quotes' => false]);

        self::assertStringContainsString('„', $defaultResult);
        self::assertStringNotContainsString('„', $overriddenResult);
        self::assertStringContainsString('"', $overriddenResult);
    }

    #[Test]
    public function use_defaults_false_skips_library_defaults(): void
    {
        $extension = new TypographyExtension([]);

        // With $use_defaults=false and an empty config, no settings are configured at all
        // — straight quotes survive.
        $result = $extension->applyTypography('"x"', [], false);

        self::assertSame('"x"', $result);
    }
```

- [ ] **Step 2: Run the tests**

```bash
composer test
```

Expected: `OK (8 tests, ...)`.

If `per_call_arguments_override_constructor_defaults` fails on the override assertion: the merge order in `applyTypography()` is `array_merge($this->loadDefaults(), $arguments)` — `$arguments` is second, so its keys win. Double-check `src/TypographyExtension.php` from Task 2 if this fails.

- [ ] **Step 3: Run PHPStan**

```bash
composer phpstan
```

Expected: `[OK] No errors`.

- [ ] **Step 4: Commit**

```bash
git add tests/TypographyExtensionTest.php
git commit -m "$(cat <<'EOF'
test: missing-file fallback + per-call override + use_defaults flag

Final three tests close the contract. Missing YAML paths fall back to
library defaults without throwing (preserves 1.1.x behaviour). Per-call
filter arguments merge OVER constructor defaults (documented but never
tested). use_defaults=false skips Settings(true) entirely, so neither
constructor defaults nor library defaults run — the input survives.

Suite is now 8 tests; matches spec section 4 verbatim.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Neutralize bundled typography.yml

**Files:**
- Modify: `typography.yml`

- [ ] **Step 1: Replace `typography.yml` content**

Replace the entire file with a marker-only comment:

```yaml
# parisek/twig-typography — bundled settings marker
#
# This file ships intentionally empty. When the extension is constructed
# without arguments, this path is loaded — an empty parse resolves to
# `[]`, which means PHP-Typography's own `Settings(true)` defaults apply
# unmodified.
#
# To override defaults, pass either:
#   - a YAML file path:    new TypographyExtension('/path/to/your.yml')
#   - a PHP settings array: new TypographyExtension(['set_smart_quotes' => true])
#
# See README.md for a Czech (cs-CZ) configuration sample.
```

- [ ] **Step 2: Verify tests still pass**

```bash
cd /Users/pari/Sites/twig-typography
composer test
```

Expected: `OK (8 tests, ...)`.

In particular `missing_yaml_path_falls_back_silently_to_library_defaults` and the default-constructor path through `filter_is_registered_with_html_safety` exercise this file indirectly — both should remain green.

- [ ] **Step 3: Commit**

```bash
git add typography.yml
git commit -m "$(cat <<'EOF'
refactor: neutralize bundled typography.yml to a marker file

The previous defaults (set_diacritic_language: cs, six FALSE flags)
were Czech-leaning and only useful to a narrow set of consumers; every
real downstream project (tailwind-base, pm-a) supplies its own richer
typography.yml via the typography_config bootstrap key.

Empty YAML resolves to `[]` in loadDefaults(), letting PHP-Typography's
own Settings(true) defaults apply unmodified — a more honest baseline
for new adopters. The Czech sample now lives in README.md as a
copy-and-tune snippet.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Rewrite README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Replace `README.md` content**

Replace the entire file with:

````markdown
# Twig Typography Extension

Twig adapter for [PHP-Typography](https://github.com/mundschenk-at/php-typography) —
smart quotes, dashes, ellipses, hyphenation, widow protection, fraction
glyphs, ordinal suffixes, math symbols, CSS hooks for styling.

## Requirements

- PHP 8.3+
- Twig 3 or 4
- Symfony YAML 6, 7, or 8 (used only when loading config from a `.yml` file)

## Installation

```bash
composer require parisek/twig-typography
```

## Usage

### Register on a Twig environment

```php
use Parisek\Twig\TypographyExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$twig = new Environment(new FilesystemLoader('/path/to/templates'));

// Library defaults — sane English settings.
$twig->addExtension(new TypographyExtension());

// — or — load settings from a YAML file:
$twig->addExtension(new TypographyExtension(__DIR__ . '/typography.yml'));

// — or — pass settings as a PHP array (no filesystem):
$twig->addExtension(new TypographyExtension([
    'set_smart_quotes' => true,
    'set_smart_dashes' => true,
]));
```

### In templates

```twig
{{ title|typography }}

{{ "Lorem ipsum"|typography }}

{# Override constructor defaults for one call: #}
{{ title|typography({ set_smart_dashes: false }) }}
```

The filter is `is_safe: html` — its output may contain `<sup>`, `<span class="…">`,
and similar markup, and is emitted unescaped.

## Configuration

Every key in your YAML or array becomes a method call on
[PHP-Typography's `Settings` class](https://github.com/mundschenk-at/php-typography/blob/main/src/class-settings.php).
The library's full `Settings(true)` defaults are applied first; your
values override them.

### Example: Czech (`cs-CZ`) settings

```yaml
# typography.yml — Czech smart typography
set_diacritic_language: "cs"

# Smart quotes — Czech style „double" and ‚single'
set_smart_quotes: TRUE
set_smart_quotes_primary: "doubleLow9"      # „ … "
set_smart_quotes_secondary: "singleLow9"    # ‚ … '

# Smart dashes — Czech/EU: en-dash with spaces (not US em-dash)
set_smart_dashes: TRUE
set_smart_dashes_style: "international"

# Smart spacing
set_single_character_word_spacing: TRUE     # k/s/v/z/o/u/i/a + nbsp — required in CZ
set_unit_spacing: TRUE                      # "5 kg" → "5&nbsp;kg"
set_dewidow: FALSE                          # last-line widow protection — bad for responsive layouts

# Wrapping helpers
set_hyphenation: FALSE                      # leave to CSS `hyphens: auto` + `lang="cs"`
set_url_wrap: FALSE
set_email_wrap: FALSE
```

## What's not included

This extension exposes PHP-Typography as **one Twig filter**, `|typography`.
There's no `{% typography %}` block tag (despite earlier versions of this
README claiming one — the tag was never implemented in code). To apply
typography to a block of HTML, wrap it in an element and apply the filter
to the rendered string, or define a [Twig macro](https://twig.symfony.com/doc/3.x/tags/macro.html)
that encapsulates the pattern you want.

## License

GPL-2.0-or-later, see `LICENSE.txt`.

## Inspiration

- [Twig Extension Symfony Bundle](https://github.com/debach/typography-bundle)
- [Twig Typography Drupal Module](https://www.drupal.org/project/twig_typography)
````

- [ ] **Step 2: Verify the snippets compile**

```bash
cd /Users/pari/Sites/twig-typography
php -r '
require "vendor/autoload.php";
use Parisek\Twig\TypographyExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

$twig = new Environment(new ArrayLoader(["test" => "{{ x|typography }}"]));
$twig->addExtension(new TypographyExtension(["set_smart_quotes" => true, "set_smart_dashes" => true]));
echo $twig->render("test", ["x" => "Hello -- world."]) . "\n";
'
```

Expected output: a line containing an en-dash (`–`) where the `--` was.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "$(cat <<'EOF'
docs: rewrite README for 1.2.0 — drop fictional block tag, add cs sample

Four issues fixed in the rewrite:

1. The {% typography %} block tag documented since 2021 was never
   implemented in code — removed and replaced with an explicit
   "What's not included" section pointing to macro workarounds.
2. The Twig 1.x/2.x `Twig_Environment($loader)` API in the Usage
   snippet replaced with `new \Twig\Environment(…)`.
3. A copy-and-tune Czech (cs-CZ) configuration sample added, lifted
   from the production tailwind-base config so adopters see what a
   real-world typography.yml looks like.
4. PHP/Twig/Symfony requirement summary added up top so adopters
   don't have to read composer.json to learn the floors.

Also adds the new array-overload usage to the Usage section.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Add CHANGELOG.md

**Files:**
- Create: `CHANGELOG.md`

- [ ] **Step 1: Create `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to `parisek/twig-typography` are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
```

The `2026-05-25` date in `[1.2.0]` matches the brainstorm and spec date.
If the actual tag goes out on a different day, update this line during the
release step.

- [ ] **Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "$(cat <<'EOF'
docs: add CHANGELOG.md (Keep a Changelog format)

Introduces a structured changelog at the 1.2.0 release. Historical
entries for 1.0.0 and 1.1.0 are stubs — full release notes for those
versions live in git tags and GitHub Releases; backfilling them would
require archaeology that delivers no current value.

The 1.2.0 entry covers every diff in this modernization release in
Keep-a-Changelog sections: Added (PHP-array overload, tests, CI matrix,
PHPStan, version constraints, autoload-dev mapping, this file itself),
Changed (src/ move, strict types, PSR-12, getDefaults → loadDefaults
rename, marker typography.yml, first-class callable syntax), Removed
(Twig 2 + Symfony 5 + fictional block tag), Fixed (README's Twig
2.x→3.x API).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Replace CI workflow with 3-job matrix

**Files:**
- Delete: `.github/workflows/php.yml`
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Delete the old workflow**

```bash
cd /Users/pari/Sites/twig-typography
git rm .github/workflows/php.yml
```

- [ ] **Step 2: Create `.github/workflows/ci.yml`**

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
          - { php: '8.3', twig: '^3.0',       symfony: '^7.0', stable: true  }
          - { php: '8.4', twig: '^3.0',       symfony: '^8.0', stable: true  }
          - { php: '8.4', twig: '^4.0@alpha', symfony: '^8.0', stable: false }
    continue-on-error: ${{ !matrix.stable }}

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - name: Validate composer.json
        run: composer validate --strict

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

- [ ] **Step 3: Locally simulate the floor matrix row to catch issues before push**

```bash
cd /Users/pari/Sites/twig-typography

# Make sure dev install is clean against current composer.json.
composer install --no-progress

# Run the same commands CI runs in the stable jobs.
composer validate --strict
composer test
composer phpstan
```

Expected for all three: no errors. `composer test` shows `OK (8 tests, ...)`. `composer phpstan` shows `[OK] No errors`.

- [ ] **Step 4: Commit**

```bash
git add .github/
git commit -m "$(cat <<'EOF'
ci: replace single-version workflow with 3-job matrix

The previous .github/workflows/php.yml ran composer validate + install
only; the test step was commented out from inception. Replaces it with
a real CI matrix that runs PHPUnit and PHPStan across the supported
runtime combinations:

  - PHP 8.3 × Twig 3 × Symfony 7  (current parisek/styleguide floor)
  - PHP 8.4 × Twig 3 × Symfony 8  (Symfony 8 happy-path — PHP ≥8.4.1)
  - PHP 8.4 × Twig 4 alpha × Sym8 (forward-compat signal, non-blocking)

The Twig 4 row runs continue-on-error until Twig 4 ships stable; flip
matrix.stable to true in one commit when that lands.

`composer require --no-update` pattern lets a single composer.json serve
all three jobs — constraints are rewritten locally before resolve.
PHPStan runs only on stable rows to avoid false failures from Twig 4
alpha type-declaration churn.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Final verification + branch push

**Files:** none (verification only)

- [ ] **Step 1: Verify the full suite locally**

```bash
cd /Users/pari/Sites/twig-typography
composer validate --strict
composer test
composer phpstan
```

Expected: all three exit cleanly. Tests show `OK (8 tests, ...)`. PHPStan shows `[OK] No errors`.

- [ ] **Step 2: Verify the file layout matches the spec**

```bash
cd /Users/pari/Sites/twig-typography
tree -I 'vendor|.git|.phpunit.cache' --noreport
```

Expected output (modulo `tree` not being installed — fall back to `find . -type f -not -path './vendor/*' -not -path './.git/*' -not -path './.phpunit.cache/*' | sort`):

```
.
├── .github
│   └── workflows
│       └── ci.yml
├── .gitignore
├── CHANGELOG.md
├── LICENSE.txt
├── README.md
├── composer.json
├── docs
│   └── superpowers
│       ├── plans
│       │   └── 2026-05-25-twig-typography-modernization.md
│       └── specs
│           └── 2026-05-25-twig-typography-reshape-design.md
├── phpstan.neon
├── phpunit.xml.dist
├── src
│   └── TypographyExtension.php
├── tests
│   ├── TypographyExtensionTest.php
│   └── fixtures
│       └── cs.yml
└── typography.yml
```

- [ ] **Step 3: Inspect commit log on the branch**

```bash
git log --oneline main..HEAD
```

Expected: 9 commits (one per implementation task) on top of the spec commit (`302e050`). Last commit is the CI workflow rewrite.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin feature/1.2.0-modernization
```

CI matrix runs in GitHub Actions. Wait for all three jobs:

- `PHP 8.3 / Twig ^3.0 / Symfony ^7.0` → expected green
- `PHP 8.4 / Twig ^3.0 / Symfony ^8.0` → expected green
- `PHP 8.4 / Twig ^4.0@alpha / Symfony ^8.0` → expected green or yellow (continue-on-error)

- [ ] **Step 5: (Out of scope for this plan)** Open PR, merge, tag v1.2.0, push tag. Handle in a separate step under user supervision per the spec's release sequence (Section 7).

---

## Done criteria

The branch is ready for review when all tasks above carry green checkboxes and:

1. `composer validate --strict` exits 0.
2. `composer test` shows `OK (8 tests, ...)`.
3. `composer phpstan` shows `[OK] No errors`.
4. The three stable CI matrix rows are green on GitHub Actions.
5. The `feature/1.2.0-modernization` branch has 9 implementation commits on top of the spec commit (`302e050`).
6. Public API (namespace `Parisek\Twig`, class `TypographyExtension`, filter `|typography`, `string $config = ''` constructor) is unchanged from 1.1.x and verified via Task 3's filter registration test.

Failing any of these criteria means the task that introduced the regression needs another iteration before push.
