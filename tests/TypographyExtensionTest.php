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
    public function library_defaults_apply_when_no_constructor_config(): void
    {
        $extension = new TypographyExtension();

        $result = $extension->applyTypography('Hello world.');

        // Library defaults (dewidow inserts NBSP between "Hello" and "world",
        // English hyphenation seeds soft-hyphens inside "Hello") meaningfully
        // transform clean input. The inequality verifies Settings(true) is
        // actually being initialised — a bug that silently skipped library
        // defaults would otherwise go undetected.
        self::assertNotSame('Hello world.', $result);
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

    #[Test]
    public function yaml_config_path_is_loaded_and_applied(): void
    {
        $extension = new TypographyExtension(__DIR__ . '/fixtures/cs.yml');

        $result = $extension->applyTypography('He said "hello".');

        self::assertStringContainsString('„', $result);
        self::assertStringContainsString('"', $result);
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
        $markup = new class('He said "hello".') implements \Stringable {
            public function __construct(private readonly string $value) {}
            public function __toString(): string { return $this->value; }
        };

        $result = $extension->applyTypography($markup);

        self::assertStringContainsString('„', $result);
    }
}
