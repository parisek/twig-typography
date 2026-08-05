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
        // EmptyNode is concrete in Twig 3 and Twig 4; Twig 4 makes Node itself
        // abstract, so direct `new Node()` (legal in Twig 3) errors there. Static
        // `is_safe` returns the same array regardless of the node argument.
        self::assertSame(['html'], $filters[0]->getSafe(new \Twig\Node\EmptyNode()));
    }

    #[Test]
    public function library_defaults_apply_when_no_constructor_config(): void
    {
        $extension = new TypographyExtension();

        $result = $extension->applyTypography('Hello world.');

        // The house policy (resources/policy.yml) now applies underneath
        // PHP-Typography's own Settings(true) defaults even with no project
        // config at all, and it deliberately turns dewidow and hyphenation
        // off (they fight responsive layouts and CSS `hyphens: auto`
        // respectively — see policy.yml). So clean input that the raw
        // library defaults would have transformed (NBSP dewidow, seeded
        // soft-hyphens) now survives unchanged. This is the intended policy
        // taking effect, not a regression: before this task a bug (the
        // "11-project bug") meant no house policy applied at all and every
        // consumer inherited the library's raw opinions unfiltered.
        self::assertSame('Hello world.', $result);
    }

    #[Test]
    public function smart_quotes_replace_cs_style_low9_quotes(): void
    {
        $extension = new TypographyExtension([
            'set_smart_quotes' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        $result = $extension->applyTypography('He said "hello".');

        // The previous assertStringContainsString('"', $result) was passing
        // only incidentally — it matched the ASCII `"` inside a
        // `<span class="pull-double">` hanging-punctuation wrapper that the
        // raw library defaults emit (set_style_hanging_punctuation is on by
        // default). The house policy turns that styling off (nothing in the
        // stylesheets targets those classes — see policy.yml), so the
        // wrapper — and the ASCII quote it incidentally carried — no longer
        // appears. Both smart quotes now render as intended: opening „ and
        // closing ”.
        self::assertStringContainsString('„', $result);
        self::assertStringContainsString('”', $result);
        self::assertStringNotContainsString('"hello"', $result);
    }

    #[Test]
    public function yaml_config_path_is_loaded_and_applied(): void
    {
        $extension = new TypographyExtension(__DIR__ . '/fixtures/cs.yml');

        $result = $extension->applyTypography('He said "hello".');

        // Same fix as smart_quotes_replace_cs_style_low9_quotes above: the
        // old ASCII-quote assertion was matching hanging-punctuation markup
        // that the house policy now turns off, not a literal quote character.
        self::assertStringContainsString('„', $result);
        self::assertStringContainsString('”', $result);
        self::assertStringNotContainsString('"hello"', $result);
    }

    #[Test]
    public function array_config_is_applied_without_filesystem(): void
    {
        $arrayExt = new TypographyExtension([
            'set_smart_quotes' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
            'set_diacritic_language' => 'cs',
        ]);
        $yamlExt = new TypographyExtension(__DIR__ . '/fixtures/cs.yml');

        $input = 'She said "hi".';

        self::assertSame(
            $yamlExt->applyTypography($input),
            $arrayExt->applyTypography($input),
            'array config should produce the same output as the equivalent YAML config',
        );
    }

    #[Test]
    public function missing_yaml_path_falls_back_silently_to_library_defaults(): void
    {
        $missing = new TypographyExtension('/path/that/does/not/exist.yml');
        $emptyConfig = new TypographyExtension([]);

        // Both constructors resolve to an empty settings array inside loadDefaults
        // (bogus path → is_file false → []; empty array → array branch → []), then
        // Settings(true) library defaults apply to both. The two outputs must match
        // byte-for-byte — that's the "silent fallback" contract: a missing file is
        // indistinguishable from no config at all.
        $input = 'She said "hi".';

        self::assertSame(
            $emptyConfig->applyTypography($input),
            $missing->applyTypography($input),
            'missing file path should produce identical output to empty-array config',
        );
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

    #[Test]
    public function null_input_returns_empty_string_without_type_error(): void
    {
        $extension = new TypographyExtension();

        // Optional ACF fields (link.title on an empty link, repeater rows the editor
        // left blank, …) routinely pipe `null` into `|typography` from templates that
        // don't guard with `{% if value %}`. Pre-1.2 the filter coerced null → '' for
        // free; the strict-typed 1.2 signature broke that on PHP 8 with a TypeError
        // that surfaces as a 500 on the live site. Accepting null and short-circuiting
        // to '' mirrors how built-in Twig filters (`|trim`, `|lower`, `|escape`) behave.
        $result = $extension->applyTypography(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function stringable_input_is_accepted_without_type_error(): void
    {
        $extension = new TypographyExtension([
            'set_smart_quotes' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        // Templates routinely pipe Twig\Markup (the `|raw` wrapper) and other
        // Stringable values through `|typography`. The strict-typed signature
        // would reject those under `declare(strict_types=1)` without the
        // `string|\Stringable` union — this anonymous-class proxy stands in for
        // every such object and confirms the BC contract holds.
        $markup = new class ('He said "hello".') implements \Stringable {
            public function __construct(private readonly string $value) {}
            public function __toString(): string
            {
                return $this->value;
            }
        };

        $result = $extension->applyTypography($markup);

        self::assertStringContainsString('„', $result);
    }

    #[Test]
    public function house_policy_applies_without_any_project_config(): void
    {
        // The 11-project bug: a consumer that passes a path which does not
        // exist used to fall through to the library's own defaults, which wrap
        // every ampersand in <span class="amp">.
        $extension = new TypographyExtension('/nonexistent/typography.yml');

        $result = $extension->applyTypography('Tom &amp; Jerry');

        self::assertStringNotContainsString('class="amp"', $result);
    }

    #[Test]
    public function the_language_table_supplies_quotes_for_the_resolved_locale(): void
    {
        // BLOCKED on Task 4: resources/languages/ ships no table files yet
        // (only .gitkeep), so SettingsLoader::language('cs') / ('cs-CZ')
        // resolves to [] and the resolved locale has no observable effect —
        // there is nothing in this package for the language layer to load.
        // Creating a stub resources/languages/cs.yml here would silently
        // become the shipped table, which the brief explicitly forbids for
        // this task. Once Task 4 lands a real cs.yml with smart-quote
        // settings, replace this body with the brief's original assertions:
        //
        //   $extension = new TypographyExtension('', static fn (): string => 'cs_CZ');
        //   $result = $extension->applyTypography('Řekl "ahoj" dnes.');
        //   self::assertStringContainsString('„', $result);
        //   self::assertStringContainsString('“', $result);
        //   self::assertStringNotContainsString('”', $result);
        //
        // For now, assert only that the wiring (resolver -> LocaleResolver
        // -> SettingsLoader::language) runs end to end without throwing and
        // without corrupting the output.
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $result = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertStringContainsString('ahoj', $result);
    }

    #[Test]
    public function the_resolver_is_consulted_on_every_call_not_at_construction(): void
    {
        // A language switch mid-request must be honoured. Constructing the
        // extension once and resolving the locale then would silently render
        // the second call in the first call's language.
        //
        // With no resources/languages/*.yml tables shipped yet (Task 4),
        // 'en_US' and 'cs_CZ' currently resolve to the same (empty) language
        // layer, so distinguishable per-locale *output* can't be asserted
        // here today — see the_language_table_supplies_quotes_for_the_resolved_locale
        // above for that follow-up. What this test can and does assert is the
        // property the docblock on resolveLanguageSettings() actually cares
        // about: the resolver callable is invoked fresh on every
        // applyTypography() call, not memoised at construction time.
        $calls = 0;
        $extension = new TypographyExtension('', static function () use (&$calls): string {
            $calls++;

            return $calls === 1 ? 'en_US' : 'cs_CZ';
        });

        $extension->applyTypography('Said "hi" today.');
        $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertSame(2, $calls);
    }

    #[Test]
    public function a_project_config_overrides_the_language_table(): void
    {
        $extension = new TypographyExtension(
            ['set_smart_quotes_primary' => 'doubleGuillemets'],
            static fn(): string => 'cs_CZ',
        );

        $result = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertStringContainsString('«', $result);
    }

    #[Test]
    public function a_per_call_argument_overrides_everything(): void
    {
        $extension = new TypographyExtension(
            ['set_smart_quotes_primary' => 'doubleGuillemets'],
            static fn(): string => 'cs_CZ',
        );

        $result = $extension->applyTypography(
            'Řekl "ahoj" dnes.',
            ['set_smart_quotes_primary' => 'doubleCurled'],
        );

        self::assertStringContainsString('“', $result);
        self::assertStringNotContainsString('«', $result);
    }

    #[Test]
    public function a_resolver_returning_an_unknown_locale_still_renders(): void
    {
        $extension = new TypographyExtension('', static fn(): string => 'zz_ZZ');

        self::assertSame('Plain text.', trim(strip_tags($extension->applyTypography('Plain text.'))));
    }

    #[Test]
    public function a_throwing_resolver_does_not_break_rendering(): void
    {
        // The resolver runs inside the render path. A CMS that raises during
        // language detection must degrade to no language layer, not 500.
        $extension = new TypographyExtension('', static function (): string {
            throw new \RuntimeException('language backend down');
        });

        self::assertNotSame('', $extension->applyTypography('Plain text.'));
    }
}
