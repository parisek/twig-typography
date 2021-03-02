Twig Typography Extension
=======================
Uses [PHP-Typography](https://github.com/mundschenk-at/php-typography) library and exposes it as Twig Extension. See [class-settings.php](https://github.com/mundschenk-at/php-typography/blob/0fa6cf412124171360eebab59ca77769c67c9740/src/class-settings.php#L247) for possible options.

*   Hyphenation — over 50 languages supported
*   Space control, including:
    -   widow protection
    -   gluing values to units
    -   forced internal wrapping of long URLs & email addresses
*   Intelligent character replacement, including smart handling of:
    -   quote marks (‘single’, “double”)
    -   dashes ( – )
    -   ellipses (…)
    -   trademarks, copyright & service marks (™ ©)
    -   math symbols (5×5×5=53)
    -   fractions (<sup>1</sup>⁄<sub>16</sub>)
    -   ordinal suffixes (1<sup>st</sup>, 2<sup>nd</sup>)
*   CSS hooks for styling:
    -   ampersands,
    -   uppercase words,
    -   numbers,
    -   initial quotes & guillemets.

## Installation

Twig Attribute Extension can be easily installed using [composer](http://getcomposer.org/)

    composer require parisek/twig-typography

## Usage

```php
$twig = new Twig_Environment($loader);
$twig->addExtension(new Parisek\Twig\AttributeExtension(__DIR__ . '/typography.yml'));
```

## Template

```twig
{{ title|typography }}
```

```twig
<h1>{{ "Lorem Ipsum"|typography }}</h1>
```

```twig
{{ title|typography({'set_dewidow': FALSE}) }}
```

```twig
{% typography %}
  <h1>Lorem Ipsum</h1>
  <p>
    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce ullamcorper semper nunc, a hendrerit leo auctor ultricies.
  </p>
{% endtypography %}
```

## Inspiration
- [Twig Extension Symfony Bundle](https://github.com/debach/typography-bundle)
- [Twig Typography Drupal Module](https://www.drupal.org/project/twig_typography)
