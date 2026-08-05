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
            $contents = file_get_contents($path);
            if ($contents !== false) {
                try {
                    $parsed = Yaml::parse($contents);
                    if (is_array($parsed)) {
                        $settings = $parsed;
                    }
                } catch (\Throwable) {
                    // Malformed YAML in a resource file degrades to "no
                    // settings from this layer", matching the absent-file path.
                    $settings = [];
                }
            }
        }

        return self::$memo[$path] = $settings;
    }
}
