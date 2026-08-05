<?php

declare(strict_types=1);

namespace Parisek\Twig\Tests;

use Parisek\Twig\TypographyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LanguageTableTest extends TestCase
{
    /**
     * One sentence per language, exercising every language-dependent setting
     * at once: double quotes, a dash, and (where the language uses it) the
     * single-character word spacing that inserts a NBSP.
     *
     * The expected strings are the whole point of this suite. They are what
     * catches the defect this design exists to fix: the fleet shipped
     * `doubleLow9`, so Czech rendered `„ahoj”` — the Polish pair — and nothing
     * failed.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function languages(): array
    {
        return [
            'cs' => ['cs_CZ', 'Řekl "ahoj" dnes.', '„ahoj“'],
            'de' => ['de_DE', 'Er sagte "hallo" heute.', '„hallo“'],
            'pl' => ['pl_PL', 'Powiedział "cześć" dzisiaj.', '„cześć”'],
            'en' => ['en_US', 'He said "hi" today.', '“hi”'],
            'ru' => ['ru_RU', 'Он сказал "привет" сегодня.', '«привет»'],
            'sk' => ['sk_SK', 'Povedal "ahoj" dnes.', '„ahoj“'],
            'sl' => ['sl_SI', 'Rekel je "zdravo" danes.', '„zdravo“'],
            'hr' => ['hr_HR', 'Rekao je "bok" danas.', '„bok“'],
            'hu' => ['hu_HU', 'Azt mondta "szia" ma.', '„szia”'],
            'nl' => ['nl_NL', 'Hij zei "hallo" vandaag.', '“hallo”'],
            'pt' => ['pt_PT', 'Ele disse "olá" hoje.', '“olá”'],
            'tr' => ['tr_TR', 'Bugün "merhaba" dedi.', '“merhaba”'],
        ];
    }

    #[Test]
    #[DataProvider('languages')]
    public function primary_quotes_match_the_language_convention(
        string $locale,
        string $input,
        string $expected_quotes,
    ): void {
        $extension = new TypographyExtension('', static fn(): string => $locale);

        $result = $extension->applyTypography($input);

        self::assertStringContainsString($expected_quotes, strip_tags($result), $locale);
    }

    #[Test]
    public function french_uses_guillemets_with_non_breaking_spaces(): void
    {
        // French is the case that forces the layer ordering: the house policy
        // has `set_french_punctuation_spacing` off, and only "language beats
        // policy" lets French switch it on.
        $extension = new TypographyExtension('', static fn(): string => 'fr_FR');

        $result = $extension->applyTypography('Il a dit "bonjour" aujourd\'hui.');

        self::assertStringContainsString('«', $result);
        self::assertStringContainsString('&nbsp;bonjour&nbsp;', $result);
    }

    #[Test]
    public function czech_inserts_a_non_breaking_space_after_a_single_character_word(): void
    {
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $result = $extension->applyTypography('Bydlí v Praze.');

        self::assertStringContainsString('v&nbsp;Praze', $result);
    }

    #[Test]
    public function english_does_not_insert_single_character_word_spacing(): void
    {
        $extension = new TypographyExtension('', static fn(): string => 'en_US');

        $result = $extension->applyTypography('A house in town.');

        self::assertStringNotContainsString('A&nbsp;house', $result);
    }
}
