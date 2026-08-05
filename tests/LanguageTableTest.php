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
            'hr' => ['hr_HR', 'Rekao je "bok" danas.', '„bok”'],
            'hu' => ['hu_HU', 'Azt mondta "szia" ma.', '„szia”'],
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

    /**
     * One sentence per language with a nested quote inside the outer one,
     * exercising `set_smart_quotes_secondary` — the setting the primary-quote
     * suite above never touches. Probed against the actual library output per
     * language, same discipline as {@see languages()}.
     *
     * Hungarian is the case this suite exists to catch: the fleet shipped
     * `singleLow9` for the secondary slot, which renders `‚belso'` — a
     * single-quote variant — instead of the correct inward guillemets
     * `»belso«`.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function nestedQuoteLanguages(): array
    {
        return [
            'cs' => ['cs_CZ', 'Řekl "dnes \'ahoj\' znovu".', '‚ahoj‘'],
            'de' => ['de_DE', 'Er sagte "heute \'hallo\' wieder".', '‚hallo‘'],
            'pl' => ['pl_PL', 'Powiedział "dzisiaj \'cześć\' znowu".', '«cześć»'],
            'en' => ['en_US', 'He said "today \'hi\' again".', '‘hi’'],
            'fr' => ['fr_FR', 'Il a dit "aujourd\'hui \'bonjour\' encore".', '‹bonjour›'],
            'ru' => ['ru_RU', 'Он сказал "сегодня \'привет\' снова".', '„привет“'],
            'sk' => ['sk_SK', 'Povedal "dnes \'ahoj\' znova".', '‚ahoj‘'],
            'sl' => ['sl_SI', 'Rekel je "danes \'zdravo\' spet".', '‚zdravo‘'],
            'hr' => ['hr_HR', 'Rekao je "danas \'bok\' opet".', '‘bok’'],
            'hu' => ['hu_HU', 'Azt mondta "ma \'szia\' ismét".', '»szia«'],
            'tr' => ['tr_TR', 'Bugün "yine \'merhaba\' dedi".', '‘merhaba’'],
        ];
    }

    #[Test]
    #[DataProvider('nestedQuoteLanguages')]
    public function secondary_quotes_match_the_language_convention(
        string $locale,
        string $input,
        string $expected_nested_quotes,
    ): void {
        $extension = new TypographyExtension('', static fn(): string => $locale);

        $result = $extension->applyTypography($input);

        self::assertStringContainsString($expected_nested_quotes, strip_tags($result), $locale);
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

    #[Test]
    public function slovak_inserts_a_non_breaking_space_after_a_single_character_word(): void
    {
        // Mirrors the Czech test above: sk.yml is the only one of the seven
        // Task 5 languages with `set_single_character_word_spacing: true`.
        $extension = new TypographyExtension('', static fn(): string => 'sk_SK');

        $result = $extension->applyTypography('Bol v meste.');

        self::assertStringContainsString('v&nbsp;meste', $result);
    }

    #[Test]
    public function croatian_does_not_insert_single_character_word_spacing(): void
    {
        // Negative pin for the other direction: hr.yml ships the setting off,
        // even though Croatian has its own single-letter preposition ("u")
        // that a careless copy from sk/pl could have left spacing-enabled for.
        $extension = new TypographyExtension('', static fn(): string => 'hr_HR');

        $result = $extension->applyTypography('Kuća u gradu.');

        self::assertStringNotContainsString('u&nbsp;gradu', $result);
    }

    #[Test]
    public function swiss_german_uses_guillemets_not_the_de_low9_pair(): void
    {
        // Regional table beats the bare-language fallback: `de_CH` must
        // resolve to `de-CH`'s «…», not fall through to `de`'s „…“.
        $extension = new TypographyExtension('', static fn(): string => 'de_CH');

        $result = $extension->applyTypography('Er sagte "hallo" heute.');

        self::assertStringContainsString('«hallo»', strip_tags($result));
    }

    #[Test]
    public function british_english_uses_a_spaced_en_dash_where_american_uses_an_unspaced_em_dash(): void
    {
        $american = new TypographyExtension('', static fn(): string => 'en_US');
        $british  = new TypographyExtension('', static fn(): string => 'en_GB');

        $input = 'Wait for it - here it comes.';

        self::assertStringContainsString('—', $american->applyTypography($input));
        self::assertStringContainsString(' – ', $british->applyTypography($input));
    }
}
