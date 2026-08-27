<?php

declare(strict_types=1);

namespace Parisek\Twig;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads YAML settings files, memoised per path, and splits a parsed
 * document into its global section and its `languages:` map.
 *
 * Every filesystem access in the package lives here, which is what lets
 * {@see LocaleResolver} stay pure and lets the merge order in
 * {@see TypographyExtension} be read without thinking about disk.
 *
 * Memoisation is not an optimisation detail — the settings are resolved on
 * EVERY `|typography` call, and a page renders the filter hundreds of times.
 *
 * The package's own bundled table and a consuming project's settings file
 * share one shape: global keys at the top level, an optional `languages:`
 * map keyed by language tag. This class is the single place that both
 * understand that shape.
 */
final class SettingsLoader
{
    /** @var array<string, array<string, mixed>> */
    private static array $memo = [];

    /**
     * Forget every parsed file.
     *
     * The memo above has no expiry, which is right for a request: it ends
     * before a settings file can change. A process that outlives one — WP-CLI,
     * a persistent worker, a test suite — needs a way to say so, and without
     * this {@see \Parisek\Twig\TypographyExtension::flushCaches()} could not
     * keep its promise: it would clear the settings objects and then rebuild
     * them from the same stale parse.
     *
     * Public only because that sibling has to reach it; PHP has no narrower
     * visibility for it. **Call `TypographyExtension::flushCaches()` instead.**
     * This clears the parsed files and nothing else, so on its own it puts the
     * package back into exactly the split state the generation counter in that
     * class exists to prevent: files re-read, settings objects still built from
     * the old ones.
     *
     * @internal
     */
    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    /**
     * The package's own bundled settings file — house policy plus the
     * per-language tables.
     */
    public static function packagePath(): string
    {
        return __DIR__ . '/../typography.yml';
    }

    /**
     * The global section of a settings file — every top-level key except
     * `languages`, which is not a real PHP-Typography setting.
     *
     * @return array<string, mixed>
     */
    public static function global(string $path): array
    {
        return self::extractGlobal(self::file($path));
    }

    /**
     * One language-table entry from a settings file's `languages:` map.
     * Unknown tags, and tags absent from the map, yield `[]` — a site in a
     * language the table has never heard of must keep rendering, just
     * without a language layer.
     *
     * @return array<string, mixed>
     */
    public static function language(string $path, string $tag): array
    {
        return self::extractLanguage(self::file($path), $tag);
    }

    /**
     * Strip the `languages:` key from an already-parsed settings document.
     * Public: shared with {@see TypographyExtension} for the array-config
     * case, which does not go through {@see file()}.
     *
     * @param  array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    public static function extractGlobal(array $parsed): array
    {
        unset($parsed['languages']);

        return $parsed;
    }

    /**
     * The validated `languages:` map from an already-parsed settings
     * document. Malformed shapes (a sequence instead of a map, either at the
     * `languages:` level or within one entry) degrade to no map / no entry,
     * matching the degrade-not-throw discipline the rest of this class uses
     * for file content.
     *
     * @param  array<string, mixed>                $parsed
     * @return array<string, array<string, mixed>>
     */
    public static function extractLanguages(array $parsed): array
    {
        $languages = $parsed['languages'] ?? [];
        if (!is_array($languages) || self::hasIntegerKey($languages)) {
            return [];
        }

        $result = [];
        foreach ($languages as $tag => $entry) {
            if (is_string($tag) && is_array($entry) && !self::hasIntegerKey($entry)) {
                $result[$tag] = $entry;
            }
        }

        return $result;
    }

    /**
     * One entry of an already-parsed document's `languages:` map, tag
     * validated the same way {@see language()} validates it for a file path.
     *
     * @param  array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    public static function extractLanguage(array $parsed, string $tag): array
    {
        // The tag derives from a runtime locale, so it is untrusted input even
        // though LocaleResolver already constrains its shape. Refusing an
        // unexpected shape here keeps that guarantee local to this method
        // rather than depending on a caller staying correct.
        if (preg_match('/^[A-Za-z]{2,3}(-[A-Za-z]{2,4})?$/', $tag) !== 1) {
            return [];
        }

        return self::extractLanguages($parsed)[$tag] ?? [];
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
