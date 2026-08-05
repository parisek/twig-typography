<?php

declare(strict_types=1);

namespace Parisek\Twig\Tests;

use Parisek\Twig\SettingsLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettingsLoaderTest extends TestCase
{
    #[Test]
    public function policy_carries_the_house_defaults(): void
    {
        $policy = SettingsLoader::policy();

        // The one that reached production unnoticed: the library defaults this
        // to TRUE, which wraps every "&" in <span class="amp">.
        self::assertArrayHasKey('set_style_ampersands', $policy);
        self::assertFalse($policy['set_style_ampersands']);
        self::assertFalse($policy['set_dewidow']);
    }

    #[Test]
    public function policy_carries_no_language_dependent_key(): void
    {
        $language_keys = [
            'set_smart_quotes_primary',
            'set_smart_quotes_secondary',
            'set_smart_dashes_style',
            'set_single_character_word_spacing',
            'set_french_punctuation_spacing',
            'set_diacritic_language',
            'set_smart_ordinal_suffix',
        ];

        foreach ($language_keys as $key) {
            self::assertArrayNotHasKey($key, SettingsLoader::policy(), $key);
        }
    }

    #[Test]
    public function an_unknown_language_tag_yields_no_settings(): void
    {
        self::assertSame([], SettingsLoader::language('zz'));
    }

    #[Test]
    public function a_traversal_attempt_in_a_tag_yields_no_settings(): void
    {
        // The tag reaches this method from a runtime locale. Even though
        // LocaleResolver already constrains its shape, the loader refuses
        // separators itself rather than trusting its caller.
        self::assertSame([], SettingsLoader::language('../policy'));
    }

    #[Test]
    public function a_missing_file_yields_no_settings(): void
    {
        self::assertSame([], SettingsLoader::file(__DIR__ . '/fixtures/does-not-exist.yml'));
    }

    #[Test]
    public function a_scalar_yaml_document_yields_no_settings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'typo') . '.yml';
        file_put_contents($path, "just a string\n");

        try {
            self::assertSame([], SettingsLoader::file($path));
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function a_file_is_read_once_and_memoised(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'typo') . '.yml';
        file_put_contents($path, "set_dewidow: true\n");

        try {
            self::assertSame(['set_dewidow' => true], SettingsLoader::file($path));
            // Rewriting the file must not change the answer: a second read
            // would mean the memo is not being used, and the loader would be
            // hitting disk on every single filter call.
            file_put_contents($path, "set_dewidow: false\n");
            self::assertSame(['set_dewidow' => true], SettingsLoader::file($path));
        } finally {
            unlink($path);
        }
    }
}
