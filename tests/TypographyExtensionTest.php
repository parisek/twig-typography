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

        // use_defaults: false disables PHP-Typography's built-in defaults
        // (soft-hyphenation, NBSP insertion, etc.) so that an empty config
        // array truly means "apply nothing".
        self::assertSame('Hello world.', $extension->applyTypography('Hello world.', [], false));
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
}
