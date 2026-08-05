<?php

declare(strict_types=1);

namespace Parisek\Twig;

/**
 * Turns a runtime locale into the ordered list of language-table tags to try.
 *
 * Pure and I/O-free on purpose: the fallback ordering is the part most likely
 * to be wrong, and keeping it free of the filesystem lets it be tested
 * exhaustively without fixtures.
 */
final class LocaleResolver
{
    /**
     * Ordered most-specific-first: `de_CH` yields `de-CH` then `de`, so a
     * region file can be added later without changing any caller.
     *
     * Accepts every shape the consumers actually produce — WordPress emits
     * POSIX (`cs_CZ`), Drupal emits a bare language id (`cs`), and a hand-set
     * value may arrive as BCP-47 (`de-CH`) or carry an encoding suffix
     * (`cs_CZ.UTF-8`). Anything unusable yields `[]`, which the caller reads
     * as "no language layer" rather than an error.
     *
     * @return array<int, string>
     */
    public static function candidates(string $locale): array
    {
        // Drop an encoding/modifier suffix (`cs_CZ.UTF-8`, `de_DE@euro`)
        // before parsing: it never participates in table lookup.
        $clean = preg_replace('/[.@].*$/', '', trim($locale)) ?? '';
        $parts = preg_split('/[_-]/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return [];
        }

        $language = strtolower($parts[0]);
        if (preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
            return [];
        }

        if (!isset($parts[1])) {
            return [$language];
        }

        // Only the first subtag after the language is kept. A three-subtag
        // locale (`zh_Hans_CN`) is narrowed to `zh-Hans`, not `zh-Hans-CN`:
        // the table is keyed by what typography actually varies on, and no
        // shipped entry is finer-grained than script or region.
        $region = $parts[1];
        $region = strlen($region) === 2 ? strtoupper($region) : ucfirst(strtolower($region));

        return [$language . '-' . $region, $language];
    }
}
