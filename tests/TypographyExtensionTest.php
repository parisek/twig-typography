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

        // The house policy (typography.yml, global section) now applies
        // underneath PHP-Typography's own Settings(true) defaults even with
        // no project config at all, and it deliberately turns dewidow and
        // hyphenation off (they fight responsive layouts and CSS
        // `hyphens: auto` respectively — see typography.yml). So clean input
        // that the raw library defaults would have transformed (NBSP
        // dewidow, seeded soft-hyphens) now survives unchanged. This is the
        // intended policy taking effect, not a regression: before this task
        // a bug (the "11-project bug") meant no house policy applied at all
        // and every consumer inherited the library's raw opinions unfiltered.
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
    public function a_project_settings_file_that_is_a_sequence_degrades_instead_of_fataling(): void
    {
        // The finding this test guards: loadDefaults() used to duplicate
        // SettingsLoader::file()'s loading logic with only an is_array()
        // check, so a project settings file shaped like "- foo\n- bar\n"
        // parsed to an integer-keyed array and fataled inside
        // applyTypography() at `$settings->{0}(...)` — "Method name must be
        // a string". The identical content in a package resource (see
        // SettingsLoaderTest::a_sequence_yaml_document_yields_no_settings)
        // was already safe. Routing through SettingsLoader::file() closes
        // that gap: this must degrade to "no project overrides", not throw.
        $path = tempnam(sys_get_temp_dir(), 'typo') . '.yml';
        file_put_contents($path, "- foo\n- bar\n");

        try {
            $sequenceConfig = new TypographyExtension($path);
            $noConfig = new TypographyExtension('');

            $input = 'She said "hi".';

            self::assertSame(
                $noConfig->applyTypography($input),
                $sequenceConfig->applyTypography($input),
                'a sequence-shaped project settings file must produce the same output as no config at all',
            );
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function a_sequence_shaped_array_config_throws_instead_of_silently_discarding(): void
    {
        // Unlike a file (host-editable data, degrades silently), an array
        // config is handed to us directly by the host application's own
        // construction call — a sequence here is a caller bug, and staying
        // silent would hide it behind a page rendered with fewer settings
        // than intended.
        $this->expectException(\InvalidArgumentException::class);

        // Decoded from JSON built at runtime from getenv() (never a literal
        // PHPStan can trace back to a fixed shape) so the static type stays
        // the constructor's own declared array<string, mixed> — matching
        // what a caller genuinely has at the call site: an array whose
        // shape is a runtime fact, not a static one. This test exercises
        // the *runtime* guard, the thing an application actually hits when
        // a caller builds the array from an external source PHPStan can't
        // see into (untrusted config content, decoded JSON/YAML, ...).
        $raw = (getenv('TYPOGRAPHY_TEST_SEQUENCE') ?: '') . '["foo", "bar"]';
        $sequenceShaped = json_decode($raw, true);
        self::assertIsArray($sequenceShaped);

        (new TypographyExtension($sequenceShaped))->applyTypography('x');
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
    public function use_defaults_false_still_applies_the_house_policy(): void
    {
        // $use_defaults only controls whether PHP-Typography's own
        // Settings(true) seeds its built-in defaults. It does not gate the
        // package's house policy (typography.yml global section), which
        // configures the Settings object explicitly regardless — including
        // the language-neutral smart-quote defaults. So even with
        // $use_defaults=false and an empty project config, quotes are still
        // smart-quoted.
        $extension = new TypographyExtension([]);

        $result = $extension->applyTypography('"x"', [], false);

        self::assertStringContainsString('“x”', $result);
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
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $result = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertStringContainsString('„', $result);
        self::assertStringContainsString('“', $result);
        self::assertStringNotContainsString('”', $result);
    }

    #[Test]
    public function the_resolver_is_consulted_on_every_call_not_at_construction(): void
    {
        // A language switch mid-request must be honoured. Constructing the
        // extension once and resolving the locale then would silently render
        // the second call in the first call's language. With en.yml and
        // cs.yml both shipped (Task 4), the two calls must also produce
        // visibly different quote conventions — not just fire the resolver
        // twice.
        $calls = 0;
        $extension = new TypographyExtension('', static function () use (&$calls): string {
            $calls++;

            return $calls === 1 ? 'en_US' : 'cs_CZ';
        });

        $englishResult = $extension->applyTypography('He said "hi" today.');
        $czechResult = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertSame(2, $calls);
        self::assertStringContainsString('“hi”', strip_tags($englishResult));
        self::assertStringContainsString('„ahoj“', strip_tags($czechResult));
    }

    #[Test]
    public function a_project_config_overrides_the_language_table(): void
    {
        $extension = new TypographyExtension(
            ['set_smart_quotes_primary' => 'doubleGuillemets'],
            static fn(): string => 'cs_CZ',
        );

        $result = $extension->applyTypography('Řekl "ahoj" - dnes.');

        // cs.yml ships doubleLow9Reversed („…“); this config asks for
        // doubleGuillemets («…») instead. The quote assertions alone are not
        // load-bearing against a bypassed language layer: if cs.yml never
        // loaded at all, «ahoj» would still come from the config and „ would
        // still be absent, so both would pass vacuously.
        //
        // A candidate third assertion was `set_single_character_word_spacing`
        // (cs.yml turns it on for the one-letter preposition "v"). Verified
        // by hand that it does NOT distinguish a loaded table from a
        // bypassed one: php-typography's own Settings(true) defaults already
        // turn that setting on regardless of any table (see
        // vendor/mundschenk-at/php-typography/src/class-settings.php,
        // set_single_character_word_spacing($on = true) called from
        // init_defaults()) — cs.yml's `true` is redundant with the library
        // default, en.yml's `false` is the one doing real work. Asserting
        // an NBSP there would have passed even with the table skipped, so
        // it was dropped in favour of a property the table actually flips
        // away from the library default: `set_smart_dashes_style`. The
        // library default is `traditionalUS` (thin-spaced em dash, —);
        // cs.yml is the only layer setting `international` (spaced en dash,
        // –), and neither the house policy nor $config here touch dashes at
        // all. So the en dash surviving in the output only happens if the
        // language table was loaded and merged underneath $config — it
        // fails both when the table is skipped and when the merge drops it.
        // Confirmed by pointing the resolver at 'zz_ZZ' (no table): this
        // assertion goes red while the quote assertions above stay green,
        // proving it — not them — is what actually detects a bypassed
        // language layer. See task-4-report.md for the full before/after.
        self::assertStringContainsString('«', $result);
        self::assertStringNotContainsString('„', $result);
        self::assertStringContainsString('–', $result);
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
        // The resolver runs inside the render path. A host application that
        // raises during locale detection must degrade to no language layer,
        // not 500.
        $extension = new TypographyExtension('', static function (): string {
            throw new \RuntimeException('language backend down');
        });

        self::assertNotSame('', $extension->applyTypography('Plain text.'));
    }

    #[Test]
    public function a_project_language_override_leaves_other_languages_untouched(): void
    {
        // A project's own settings file/array uses the same flat
        // `languages:` shape as the package's bundled table. Overriding one
        // language there must not disturb another language that the project
        // never mentions — that only-flat-config-existed limitation is the
        // whole reason this design exists.
        $locale = 'cs_CZ';
        $extension = new TypographyExtension(
            ['languages' => ['cs' => ['set_smart_quotes_primary' => 'doubleGuillemets']]],
            static function () use (&$locale): string {
                return $locale;
            },
        );

        $czech = $extension->applyTypography('Řekl "ahoj" dnes.');
        $locale = 'pl_PL';
        $polish = $extension->applyTypography('Powiedział "cześć" dzisiaj.');

        // Czech: project override wins over the package's own „…“ table.
        self::assertStringContainsString('«', $czech);
        self::assertStringNotContainsString('„', $czech);
        // Polish: untouched by the project config, still the package's „…”.
        self::assertStringContainsString('„cześć”', $polish);
    }

    #[Test]
    public function a_partial_language_entry_inherits_the_rest_from_global(): void
    {
        // The project entry for `cs` only restates the quote character; it
        // never mentions `set_single_character_word_spacing`. That key must
        // still come through — from the package's own `cs` table entry,
        // merged underneath the project layer key-by-key, not replaced
        // wholesale.
        $extension = new TypographyExtension(
            ['languages' => ['cs' => ['set_smart_quotes_primary' => 'doubleGuillemets']]],
            static fn(): string => 'cs_CZ',
        );

        $result = $extension->applyTypography('Bydlí v Praze a řekl "ahoj".');

        self::assertStringContainsString('«', $result);
        self::assertStringContainsString('v&nbsp;Praze', $result);
    }

    #[Test]
    public function a_language_absent_from_the_project_table_runs_on_global_alone(): void
    {
        // The project settings only cover `cs`; a request rendered in
        // English must fall through to the package's own `en` table (and,
        // failing that, the global neutral defaults) rather than being
        // affected by the project's `cs`-only override.
        $extension = new TypographyExtension(
            ['languages' => ['cs' => ['set_smart_quotes_primary' => 'doubleGuillemets']]],
            static fn(): string => 'en_US',
        );

        $result = $extension->applyTypography('He said "hi" today.');

        self::assertStringContainsString('“hi”', strip_tags($result));
        self::assertStringNotContainsString('«', $result);
    }

    #[Test]
    public function an_unknown_settings_key_is_skipped_and_surrounding_settings_still_apply(): void
    {
        // `set_typo` (a typo'd option name — no such method on Settings) must
        // not fatal the render, and the valid sibling key in the same call
        // must still take effect.
        $extension = new TypographyExtension();

        $result = $extension->applyTypography('He said "hi".', [
            'set_typo' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        self::assertStringContainsString('„hi”', strip_tags($result));
    }

    #[Test]
    public function a_key_colliding_with_a_protected_method_is_skipped_and_surrounding_settings_still_apply(): void
    {
        // `get_style` is a real method on Settings — but protected, not part
        // of the public setter surface. method_exists() alone can't tell the
        // difference (it returns true regardless of visibility), so it would
        // let this through and fatal on "Call to protected method
        // PHP_Typography\Settings::get_style()". The valid sibling key in
        // the same call must still take effect.
        $extension = new TypographyExtension();

        $result = $extension->applyTypography('He said "hi".', [
            'get_style' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        self::assertStringContainsString('„hi”', strip_tags($result));
    }

    #[Test]
    public function a_key_colliding_with_a_magic_method_is_skipped_and_surrounding_settings_still_apply(): void
    {
        // `__construct` is public on Settings, so a bare is_callable() check
        // (without the `set_` prefix requirement) would let it through and
        // either fatal on argument mismatch or silently reinitialise the
        // object mid-render. The valid sibling key in the same call must
        // still take effect.
        $extension = new TypographyExtension();

        $result = $extension->applyTypography('He said "hi".', [
            '__construct' => true,
            'set_smart_quotes_primary' => 'doubleLow9',
        ]);

        self::assertStringContainsString('„hi”', strip_tags($result));
    }

    #[Test]
    public function a_project_settings_file_language_override_leaves_other_languages_untouched(): void
    {
        // End-to-end version of a_project_language_override_leaves_other_languages_untouched()
        // above, routed through an actual project settings YAML file instead
        // of an array — that's what a real downstream project ships. The
        // fixture overrides only `cs`; `pl` must still come from the
        // package's own bundled table, untouched.
        $locale = 'cs_CZ';
        $extension = new TypographyExtension(
            __DIR__ . '/fixtures/project-with-cs-override.yml',
            static function () use (&$locale): string {
                return $locale;
            },
        );

        $czech = $extension->applyTypography('Řekl "ahoj" dnes.');
        $locale = 'pl_PL';
        $polish = $extension->applyTypography('Powiedział "cześć" dzisiaj.');

        // Czech: project override wins over the package's own „…“ table.
        self::assertStringContainsString('«', $czech);
        self::assertStringNotContainsString('„', $czech);
        // Polish: untouched by the project config, still the package's „…”.
        self::assertStringContainsString('„cześć”', $polish);
    }
}
