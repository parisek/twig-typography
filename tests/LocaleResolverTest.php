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
            'path traversal region'    => ['en_/etc/passwd', ['en']],
            'control char region'      => ["en_\0evil", ['en']],
            'punctuation region'       => ['en_!!', ['en']],
            'valid language bad region' => ['en_1', ['en']],
            // PCRE `$` matches before one trailing `\n` even without `/m` —
            // without the `D` modifier this would let a control character
            // ride through into the returned tag.
            'trailing newline in region'   => ["en_US\n_X", ['en']],
            'trailing newline in language' => ["en\n_US", []],
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
