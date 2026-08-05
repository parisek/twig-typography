# Twig Typography Extension

[![Packagist Version](https://img.shields.io/packagist/v/parisek/twig-typography.svg)](https://packagist.org/packages/parisek/twig-typography)
[![PHP Version](https://img.shields.io/packagist/php-v/parisek/twig-typography.svg)](https://packagist.org/packages/parisek/twig-typography)
[![Tests](https://github.com/parisek/twig-typography/actions/workflows/tests.yml/badge.svg)](https://github.com/parisek/twig-typography/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/parisek/twig-typography.svg)](https://github.com/parisek/twig-typography/blob/main/LICENSE.txt)

Twig adapter for [PHP-Typography](https://github.com/mundschenk-at/php-typography) —
smart quotes, dashes, ellipses, hyphenation, widow protection, fraction
glyphs, ordinal suffixes, math symbols, CSS hooks for styling.

## Requirements

- PHP 8.3+
- Twig 3 or 4
- Symfony YAML 6, 7, or 8 (always installed as a hard dependency; only invoked at runtime when the constructor receives a `.yml` file path)

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

// House policy only — no per-language typesetting.
$twig->addExtension(new TypographyExtension());

// — or — house policy + per-language typesetting, resolved fresh on every call:
$twig->addExtension(new TypographyExtension('', fn () => $currentLocale));

// — or — layer a project settings file on top:
$twig->addExtension(new TypographyExtension(__DIR__ . '/typography.yml', fn () => $currentLocale));

// — or — layer a PHP array on top instead (no filesystem):
$twig->addExtension(new TypographyExtension([
    'set_smart_quotes' => true,
    'set_smart_dashes' => true,
], fn () => $currentLocale));
```

The second constructor argument is a locale resolver — a callable returning the
current locale as a string (`cs_CZ`, `de-CH`, a bare `cs`, …). It is invoked on
**every** `|typography` call, not cached, so a request that changes locale
mid-render (e.g. rendering two languages of the same page) always typesets each
call in the right one. Pass `null` (the default) to skip the language layer
entirely. A resolver that throws degrades to no language layer for that call
rather than breaking the render.

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

The package ships two things beyond the `Settings` class defaults:

- **A house policy** (`resources/policy.yml`) — settings that are a house
  decision rather than a property of any one language (e.g. unit spacing on,
  dewidowing off). Applied on every render, regardless of what you pass in.
- **Eleven per-language tables** (`resources/languages/<tag>.yml`) — quote
  styles, dash conventions, single-character word spacing, and other settings
  that genuinely vary by language. Covers `cs`, `sk`, `pl`, `de`, `en`, `fr`,
  `ru`, `sl`, `hr`, `hu`, `tr`. Looked up from the locale resolver
  (see above) via `LocaleResolver::candidates()`, which tries the
  region/script-qualified tag first and falls back to the bare language — e.g.
  `de_CH` tries `de-CH` then `de`. An unrecognised language yields no language
  layer; the house policy still applies. Dutch and Portuguese are deliberately
  not included: their quote conventions are not settled enough to ship (mixed
  practice in Dutch; European vs. Brazilian Portuguese disagree) — see the
  CHANGELOG for the full rationale. A missing table just means no language
  layer, not a wrong one.

Every key in your own YAML file or array — and in the two layers above —
becomes a method call on
[PHP-Typography's `Settings` class](https://github.com/mundschenk-at/php-typography/blob/main/src/class-settings.php).

### Merge order

Later layers win on a per-key basis; a layer that doesn't touch a key leaves
the earlier value in place.

```
1. PHP-Typography's own Settings(true) defaults
2. resources/policy.yml           — house policy, every render
3. resources/languages/<tag>.yml  — resolved from the locale resolver
4. $config                        — your constructor argument (path or array)
5. |typography({ ... })           — per-call arguments
```

You do **not** need to write a settings file just to typeset one of the
eleven covered languages — pass a locale resolver and steps 1–3 already
produce a correct result. Write your own file (or array) only when your
project departs from the house style: a different quote character, hyphenation
switched on, a language the table doesn't cover, or a one-off override that
should apply to every call rather than just one.

```yaml
# typography.yml — project override, layered on top of the house policy
# and the resolved language table
set_hyphenation: TRUE   # this project wants CSS-independent hyphenation
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
