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
