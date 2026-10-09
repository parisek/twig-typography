<?php

declare(strict_types=1);

namespace Parisek\Twig\Tests;

use Parisek\Twig\TypographyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the typographic output of the upstream library for representative
 * input, one case per setting key in `typography.yml` and per language.
 *
 * The other suites check that a setting reaches the library. This one checks
 * what the library then does with it. It exists so that a bump of
 * mundschenk-at/php-typography is judged on a diff of real output, not on a
 * hope: when a golden string changes, read the change and decide whether the
 * new output is an improvement or a regression before you update the fixture.
 *
 * Expected strings live in `fixtures/php-typography-golden.php`. Invisible
 * characters (soft hyphen, zero-width space) are written as `\u{...}` escapes
 * there so a diff stays reviewable.
 */
final class PhpTypographyCharacterisationTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string, array<string, mixed>|null, string}>
     */
    public static function golden(): array
    {
        /** @var array<string, array{string|null, string, array<string, mixed>|null, string}> $rows */
        $rows = require __DIR__ . '/fixtures/php-typography-golden.php';

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $arguments null means `$use_defaults = false`
     */
    #[Test]
    #[DataProvider('golden')]
    public function output_matches_the_recorded_golden_string(
        ?string $locale,
        string $input,
        ?array $arguments,
        string $expected,
    ): void {
        $extension = new TypographyExtension(
            '',
            $locale === null ? null : static fn(): string => $locale,
        );
        $extension->flushCaches();

        $result = $extension->applyTypography($input, $arguments ?? [], $arguments !== null);

        self::assertSame($expected, $result);
    }
}
