<?php

declare(strict_types=1);

namespace Parisek\Twig\Tests;

use Parisek\Twig\LocaleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocaleResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function locales(): array
    {
        return [
            'language only'            => ['cs', ['cs']],
            'posix locale'             => ['cs_CZ', ['cs-CZ', 'cs']],
            'bcp47 locale'             => ['de-CH', ['de-CH', 'de']],
            'mixed case'               => ['DE_ch', ['de-CH', 'de']],
            'utf8 suffix'              => ['cs_CZ.UTF-8', ['cs-CZ', 'cs']],
            'three subtags keeps two'  => ['zh_Hans_CN', ['zh-Hans', 'zh']],
            'empty string'             => ['', []],
            'whitespace only'          => ['   ', []],
            'garbage'                  => ['!!', []],
        ];
    }

    /**
     * @param array<int, string> $expected
     */
    #[Test]
    #[DataProvider('locales')]
    public function candidates_are_ordered_most_specific_first(string $locale, array $expected): void
    {
        self::assertSame($expected, LocaleResolver::candidates($locale));
    }
}
