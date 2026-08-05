<?php

declare(strict_types=1);

namespace Parisek\Twig;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads the package's YAML resources, memoised per path.
 *
 * Every filesystem access in the package lives here, which is what lets
 * {@see LocaleResolver} stay pure and lets the merge order in
 * {@see TypographyExtension} be read without thinking about disk.
 *
 * Memoisation is not an optimisation detail — the settings are resolved on
 * EVERY `|typography` call, and a page renders the filter hundreds of times.
 */
final class SettingsLoader
{
    /** @var array<string, array<string, mixed>> */
    private static array $memo = [];

    /**
     * House policy: the settings that are a house decision rather than a
     * property of the language.
     *
     * @return array<string, mixed>
     */
    public static function policy(): array
    {
        return self::file(__DIR__ . '/../resources/policy.yml');
    }

    /**
     * One language-table entry. Unknown tags yield `[]` — a site in a language
     * the table has never heard of must keep rendering, just without a
     * language layer.
     *
     * @return array<string, mixed>
     */
    public static function language(string $tag): array
    {
        // The tag derives from a runtime locale, so it is untrusted input even
        // though LocaleResolver already constrains its shape. Refusing path
        // separators here keeps that guarantee local to the method that builds
        // the path, rather than depending on a caller staying correct.
        if (preg_match('/^[A-Za-z]{2,3}(-[A-Za-z]{2,4})?$/', $tag) !== 1) {
            return [];
        }

        return self::file(__DIR__ . '/../resources/languages/' . $tag . '.yml');
    }

    /**
     * Any YAML settings file. Absent, unreadable, malformed or non-map content
     * all resolve to `[]` rather than throwing: a broken resource must degrade
     * the typography, never take the page down.
     *
     * @return array<string, mixed>
     */
    public static function file(string $path): array
    {
        if (array_key_exists($path, self::$memo)) {
            return self::$memo[$path];
        }

        $settings = [];
        if (is_file($path)) {
            try {
                // Suppressed: an unreadable file raises an E_WARNING here in
                // the default (non-strict) case, which the `$contents !==
                // false` check below already handles. The surrounding `try`
                // covers the strict case, where a host application that
                // promotes warnings to exceptions turns this into a
                // \Throwable instead.
                $contents = @file_get_contents($path);
                if ($contents !== false) {
                    $parsed = Yaml::parse($contents);
                    if (is_array($parsed) && !self::hasIntegerKey($parsed)) {
                        $settings = $parsed;
                    }
                }
            } catch (\Throwable) {
                // An unreadable file (warning promoted to exception by the
                // host application) or malformed YAML both degrade to "no
                // settings from this layer", matching the absent-file path.
                $settings = [];
            }
        }

        return self::$memo[$path] = $settings;
    }

    /**
     * True when the parsed array is a list/sequence rather than a map — a
     * YAML document that starts with `-` instead of `key: value`, or an
     * array built the same way in PHP. Consumers merge this array and then
     * call it as a settings map keyed by option name, so an integer key
     * must never survive undetected: it would reach the merge as
     * `$settings->{0}(...)` and fail there. An empty array has no keys at
     * all and is therefore not a sequence by this definition.
     *
     * Public: shared with {@see TypographyExtension} for the array-config
     * case, which — unlike a file — is caller code and gets a loud
     * exception instead of a silent degrade (see that call site).
     *
     * @param array<array-key, mixed> $parsed
     */
    public static function hasIntegerKey(array $parsed): bool
    {
        foreach (array_keys($parsed) as $key) {
            if (is_int($key)) {
                return true;
            }
        }

        return false;
    }
}
