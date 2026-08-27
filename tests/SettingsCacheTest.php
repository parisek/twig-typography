<?php

declare(strict_types=1);

namespace Parisek\Twig\Tests;

use Parisek\Twig\TypographyExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The settings cache, and the four things that must stay outside it.
 *
 * Building a Settings object and a fix registry per filter call was the whole
 * cost this cache removes: one page render called the filter 1727 times, and
 * each call rebuilt both. Everything here exists to prove the cache cannot
 * hand one call another call's answer.
 */
final class SettingsCacheTest extends TestCase
{
    #[Test]
    public function the_same_call_twice_gives_the_same_answer(): void
    {
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $first = $extension->applyTypography('Řekl "ahoj" dnes.');
        $second = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertSame($first, $second);
    }

    #[Test]
    public function a_cached_settings_object_still_typesets_a_different_string(): void
    {
        // The second call is the one that reads from the cache. If the cache
        // held a result rather than settings, or if processing mutated the
        // settings, this is where it would show.
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $extension->applyTypography('Řekl "ahoj" dnes.');
        $second = $extension->applyTypography('A "potom" odešel.');

        self::assertStringContainsString('„potom“', $second);
    }

    #[Test]
    public function a_locale_change_between_calls_is_not_served_from_the_cache(): void
    {
        // The failure this guards is the expensive kind: a language switcher
        // renders every language in turn within one request, so a key without
        // the locale would typeset all of them in whichever came first.
        $locale = 'cs_CZ';
        $extension = new TypographyExtension('', static function () use (&$locale): string {
            return $locale;
        });

        $czech = $extension->applyTypography('Řekl "ahoj" dnes.');
        $locale = 'en_US';
        $english = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertStringContainsString('„', $czech);
        self::assertStringNotContainsString('„', $english);
        self::assertNotSame($czech, $english);
    }

    #[Test]
    public function per_call_arguments_are_part_of_the_key(): void
    {
        // Same string, same locale, different arguments. A key without the
        // arguments would serve the first call's settings to the second.
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $plain = $extension->applyTypography('Řekl "ahoj" dnes.');
        $guillemets = $extension->applyTypography(
            'Řekl "ahoj" dnes.',
            ['set_smart_quotes_primary' => 'doubleGuillemets'],
        );

        self::assertNotSame($plain, $guillemets);
        self::assertStringContainsString('»', $guillemets);
    }

    #[Test]
    public function use_defaults_is_part_of_the_key(): void
    {
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        // The input matters. Quotes come from the house policy's language
        // table, which applies either way, so a quoted string cannot tell the
        // two apart -- an earlier version of this test used one and passed for
        // the wrong reason. The ellipsis comes from the library defaults and
        // only survives with them on.
        $withDefaults = $extension->applyTypography('1/2 a 3x4 -- test...', [], true);
        $withoutDefaults = $extension->applyTypography('1/2 a 3x4 -- test...', [], false);

        self::assertNotSame($withDefaults, $withoutDefaults);
        self::assertStringContainsString('…', $withDefaults);
        self::assertStringContainsString('...', $withoutDefaults);
    }

    #[Test]
    public function two_instances_with_different_config_do_not_share_a_cache(): void
    {
        // The cache is per instance because the project config is. A static
        // one would need the config in every key, which means serializing an
        // arbitrary array on every call to save building one object.
        $default = new TypographyExtension('', static fn(): string => 'cs_CZ');
        $overridden = new TypographyExtension(
            ['languages' => ['cs' => ['set_smart_quotes_primary' => 'doubleGuillemets']]],
            static fn(): string => 'cs_CZ',
        );

        $a = $default->applyTypography('Řekl "ahoj" dnes.');
        $b = $overridden->applyTypography('Řekl "ahoj" dnes.');

        self::assertNotSame($a, $b);
        self::assertStringContainsString('»', $b);
    }

    #[Test]
    public function two_different_closures_do_not_share_a_cache_entry(): void
    {
        // The defect this pins, and the reason the test that used to sit here
        // was worthless. json_encode() does NOT refuse a Closure: it encodes
        // one as {} and so does any other plain object. Two different
        // PARSER_ERRORS_HANDLER closures therefore produced an identical key,
        // and the second call was served the first call's settings -- carrying
        // the FIRST call's handler, which then ran on the second call's errors.
        //
        // The old test asserted the two calls returned the same string and
        // passed, because the handler it installed never ran. It asserted the
        // defect and read it as proof the bypass worked.
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $firstRan = false;
        $secondRan = false;

        // Malformed markup, so the handler is actually reached.
        $extension->applyTypography('<p><div>x</p>', [
            'set_parser_errors_handler' => static function (array $errors) use (&$firstRan): array {
                $firstRan = true;

                return [];
            },
        ]);

        $extension->applyTypography('<p><div>x</p>', [
            'set_parser_errors_handler' => static function (array $errors) use (&$secondRan): array {
                $secondRan = true;

                return [];
            },
        ]);

        self::assertTrue($firstRan, 'the first handler must run');
        self::assertTrue($secondRan, 'the second call must use ITS OWN handler, not the cached one');
    }

    #[Test]
    public function the_settings_are_really_cached_and_a_flush_really_clears_them(): void
    {
        // Every other test here would pass without the cache, because a cache
        // that works changes nothing an observer can see. This one makes it
        // observable the only way it can be: by changing the settings file
        // underneath and showing the second call did not read it.
        //
        // It doubles as the honest statement of the staleness boundary. Within
        // one unit of work that is what "cached" means; a process that outlives
        // a settings change calls flushCaches().
        $path = tempnam(sys_get_temp_dir(), 'typo') . '.yml';
        file_put_contents($path, "languages:\n  cs:\n    set_smart_quotes_primary: doubleGuillemets\n");

        try {
            $extension = new TypographyExtension($path, static fn(): string => 'cs_CZ');

            $first = $extension->applyTypography('Řekl "ahoj" dnes.');
            self::assertStringContainsString('»', $first, 'the file is in force');

            file_put_contents($path, "languages:\n  cs:\n    set_smart_quotes_primary: doubleCurled\n");

            $cached = $extension->applyTypography('Řekl "ahoj" dnes.');
            self::assertSame($first, $cached, 'the changed file was not read: the settings came from the cache');

            $extension->flushCaches();

            $afterFlush = $extension->applyTypography('Řekl "ahoj" dnes.');
            self::assertStringContainsString('“', $afterFlush);
            self::assertNotSame($first, $afterFlush, 'the flush let the changed file through');
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_cache_does_not_grow_without_bound(): void
    {
        // $arguments is caller-supplied and part of the key, so a call site
        // passing a per-call value mints a new entry every time. Harmless in a
        // request; in a persistent worker -- the environment flushCaches()
        // exists for -- it would grow for the life of the process.
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        for ($i = 0; $i < 250; $i++) {
            $extension->applyTypography('Řekl "ahoj" dnes.', ['set_min_length_hyphenation' => $i]);
        }

        $cache = (new \ReflectionProperty(TypographyExtension::class, 'settingsCache'))
            ->getValue($extension);

        self::assertLessThanOrEqual(100, count($cache));
    }

    #[Test]
    public function a_bounded_cache_still_answers_correctly_after_it_overflows(): void
    {
        // Dropping the cache must cost a rebuild, never an answer.
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $first = $extension->applyTypography('Řekl "ahoj" dnes.');

        for ($i = 0; $i < 250; $i++) {
            $extension->applyTypography('x', ['set_min_length_hyphenation' => $i]);
        }

        self::assertSame($first, $extension->applyTypography('Řekl "ahoj" dnes.'));
    }

    #[Test]
    public function flushing_one_instance_reaches_the_others(): void
    {
        // A flush is process-wide in its effects: it drops the shared
        // processor and the parsed-file memo, which every instance reads. So
        // it has to be process-wide in its reach. Without that, flushing one
        // instance left its siblings answering from settings built out of the
        // parse that had just been thrown away -- and a caller holding one
        // instance had no way to reach the others.
        $path = tempnam(sys_get_temp_dir(), 'typo') . '.yml';
        file_put_contents($path, "languages:\n  cs:\n    set_smart_quotes_primary: doubleGuillemets\n");

        try {
            $one = new TypographyExtension($path, static fn(): string => 'cs_CZ');
            $two = new TypographyExtension($path, static fn(): string => 'cs_CZ');

            self::assertStringContainsString('»', $one->applyTypography('Řekl "ahoj" dnes.'));
            self::assertStringContainsString('»', $two->applyTypography('Řekl "ahoj" dnes.'));

            file_put_contents($path, "languages:\n  cs:\n    set_smart_quotes_primary: doubleCurled\n");

            $one->flushCaches();

            self::assertStringContainsString(
                '“',
                $two->applyTypography('Řekl "ahoj" dnes.'),
                'the instance that was not flushed must not keep serving the old parse',
            );
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function flushing_lets_a_changed_answer_through(): void
    {
        $extension = new TypographyExtension('', static fn(): string => 'cs_CZ');

        $before = $extension->applyTypography('Řekl "ahoj" dnes.');
        $extension->flushCaches();
        $after = $extension->applyTypography('Řekl "ahoj" dnes.');

        self::assertSame($before, $after, 'a flush must not change a correct answer');
    }
}
