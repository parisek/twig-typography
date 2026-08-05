<?php

declare(strict_types=1);

namespace Parisek\Twig;

use PHP_Typography\PHP_Typography;
use PHP_Typography\Settings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TypographyExtension extends AbstractExtension
{
    /**
     * Either a YAML file path (string) or an associative settings array.
     * Resolved into a flat settings array via {@see loadProjectGlobal()} and
     * {@see resolveProjectLanguageSettings()} once per filter invocation.
     *
     * @var string|array<string, mixed>
     */
    private string|array $config;

    /**
     * @var (callable(): string)|null
     *   Returns the locale to typeset for, e.g. `cs_CZ`. The locale is injected
     *   rather than detected, so the package carries no knowledge of its host
     *   application. Invoked on EVERY filter call — see
     *   {@see resolveLanguageSettings()}.
     */
    private $localeResolver;

    /**
     * @param string|array<string, mixed> $config
     *   - string ''       → no project overrides.
     *   - string '/path'  → load the YAML file at the given absolute path.
     *   - array  []|[...] → use the array as settings directly; no filesystem I/O.
     *
     * A non-existent file path resolves to no overrides. It no longer means
     * "library defaults": the house policy in the package's bundled
     * `typography.yml` applies regardless, which is the point of this layer.
     *
     * @param (callable(): string)|null $locale_resolver
     *   Supplies the current locale. Absent means no language layer.
     */
    public function __construct(string|array $config = '', ?callable $locale_resolver = null)
    {
        $this->config = $config;
        $this->localeResolver = $locale_resolver;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'typography',
                $this->applyTypography(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * Apply PHP-Typography to a string.
     *
     * @param string|\Stringable|null $string       Plain string, any Stringable (Twig\Markup from `|raw`-wrapped HTML, value objects with __toString, …), or null. Null short-circuits to '' so optional/unfilled template fields (`{{ content.button.title|typography }}` where the editor left the field empty, so the template passes null) don't fatal under strict types — matches the null-tolerance convention of built-in Twig filters like `|trim`, `|lower`, `|escape`. For non-null inputs, the cast happens at entry so the rest of the method works on a plain string.
     * @param array<string, mixed>    $arguments    Per-call setting overrides; merged on top of constructor defaults.
     * @param bool                    $use_defaults Initialise PHP-Typography's own sane defaults before applying ours.
     */
    public function applyTypography(string|\Stringable|null $string, array $arguments = [], bool $use_defaults = true): string
    {
        if ($string === null) {
            return '';
        }

        $string = (string) $string;

        $settings = new Settings($use_defaults);

        $packagePath = SettingsLoader::packagePath();
        $candidates = $this->localeCandidates();

        $merged = array_merge(
            SettingsLoader::global($packagePath),
            $this->resolveLanguageSettings($packagePath, $candidates),
            $this->loadProjectGlobal(),
            $this->resolveProjectLanguageSettings($candidates),
            $arguments,
        );
        foreach ($merged as $setting => $value) {
            // `languages` is not a real Settings method — it is a document
            // structure key, already stripped by SettingsLoader::global()/
            // extractGlobal() for every layer above. This guard only covers
            // per-call $arguments, which bypass that stripping.
            if ($setting === 'languages') {
                continue;
            }

            // An unrecognised key must not fatal the render — e.g. a typo'd
            // option name, or a key meant for a future PHP-Typography
            // version this package hasn't caught up to yet.
            if (!method_exists($settings, $setting)) {
                continue;
            }

            $settings->{$setting}($value);
        }

        // mundschenk-at/php-typography's latest release is v6.7.0 (Nov 2022),
        // predating PHP 8.4. Its method signatures still use implicitly-nullable
        // parameters (e.g. `callable $handler = null`), which PHP 8.4+ deprecates.
        // With display_errors on, those E_DEPRECATED notices are written straight
        // into the output stream and corrupt the rendered HTML. Suppress only
        // E_DEPRECATED for the duration of the upstream call, then restore the
        // previous level so genuine errors elsewhere are unaffected.
        //
        // This is purely a stopgap for the unmaintained 2022 dependency — drop it
        // once php-typography ships the nullable type-hint fix and we bump to it:
        // https://github.com/mundschenk-at/php-typography/pull/189
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED);

        try {
            return (new PHP_Typography())->process($string, $settings);
        } finally {
            error_reporting($previousErrorReporting);
        }
    }

    /**
     * The ordered candidate tags for the current call's locale (most specific
     * first), or `[]` when there is no resolver, it throws, or it returns an
     * unusable locale.
     *
     * Resolved once per {@see applyTypography()} call, not cached on the
     * instance: the host application can change the active locale between two
     * renders within a single request, and a value captured at construction
     * would typeset the second render in the first one's language. Computed
     * once per call (not once per layer) so the package table and the
     * project table are resolved against the same locale.
     *
     * A resolver that throws degrades to no language layer. It runs inside the
     * render path, so a failing language backend must cost typography, not the
     * whole page.
     *
     * @return array<int, string>
     */
    private function localeCandidates(): array
    {
        if ($this->localeResolver === null) {
            return [];
        }

        try {
            $locale = ($this->localeResolver)();
        } catch (\Throwable) {
            return [];
        }

        return LocaleResolver::candidates($locale);
    }

    /**
     * The first candidate tag present in the `languages:` map of the settings
     * file at $path, or `[]` when none match — matching {@see LocaleResolver}'s
     * "first tag present wins" contract.
     *
     * @param  array<int, string>   $candidates
     * @return array<string, mixed>
     */
    private function resolveLanguageSettings(string $path, array $candidates): array
    {
        foreach ($candidates as $tag) {
            $settings = SettingsLoader::language($path, $tag);
            if ($settings !== []) {
                return $settings;
            }
        }

        return [];
    }

    /**
     * The global section of the constructor's `$config` argument.
     *
     * The string (file) case is delegated to {@see SettingsLoader::global()}
     * so it gets the same guards as every other resource the package reads:
     * a missing/unreadable/malformed/non-map file degrades to no overrides
     * instead of throwing.
     *
     * The array case is different code, not different data: it is handed
     * to us directly by the host application's own construction call, not
     * parsed from a file another process could have hand-edited. Silently
     * discarding it the way {@see SettingsLoader::file()} discards a bad
     * file would hide a caller bug behind a page that renders with fewer
     * settings than intended and no way to notice. So a sequence array here
     * throws instead of degrading — the caller finds out immediately, at
     * the call site, rather than downstream in a rendered page.
     *
     * @return array<string, mixed>
     */
    private function loadProjectGlobal(): array
    {
        if (is_array($this->config)) {
            if (SettingsLoader::hasIntegerKey($this->config)) {
                throw new \InvalidArgumentException(
                    'Typography settings array must be a map of option name to value, not a sequence.',
                );
            }

            return SettingsLoader::extractGlobal($this->config);
        }

        if ($this->config === '') {
            return [];
        }

        return SettingsLoader::global($this->config);
    }

    /**
     * The `languages:` layer of the constructor's `$config` argument, for the
     * current call's locale candidates. Mirrors {@see loadProjectGlobal()}'s
     * string-vs-array handling.
     *
     * @param  array<int, string>   $candidates
     * @return array<string, mixed>
     */
    private function resolveProjectLanguageSettings(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        if (is_array($this->config)) {
            foreach ($candidates as $tag) {
                $settings = SettingsLoader::extractLanguage($this->config, $tag);
                if ($settings !== []) {
                    return $settings;
                }
            }

            return [];
        }

        if ($this->config === '') {
            return [];
        }

        return $this->resolveLanguageSettings($this->config, $candidates);
    }
}
