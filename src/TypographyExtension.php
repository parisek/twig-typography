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
     * Resolved into a flat settings array via {@see loadDefaults()} once
     * per filter invocation.
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
     * "library defaults": the house policy in `resources/policy.yml` applies
     * regardless, which is the point of this layer.
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

        $merged = array_merge(
            SettingsLoader::policy(),
            $this->resolveLanguageSettings(),
            $this->loadDefaults(),
            $arguments,
        );
        foreach ($merged as $setting => $value) {
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
     * The language layer for the current call.
     *
     * Resolved per invocation, not cached on the instance: the host
     * application can change the active locale between two renders within a
     * single request, and a value captured at construction would typeset the
     * second render in the first one's language.
     *
     * A resolver that throws degrades to no language layer. It runs inside the
     * render path, so a failing language backend must cost typography, not the
     * whole page.
     *
     * @return array<string, mixed>
     */
    private function resolveLanguageSettings(): array
    {
        if ($this->localeResolver === null) {
            return [];
        }

        try {
            $locale = ($this->localeResolver)();
        } catch (\Throwable) {
            return [];
        }

        foreach (LocaleResolver::candidates($locale) as $tag) {
            $settings = SettingsLoader::language($tag);
            if ($settings !== []) {
                return $settings;
            }
        }

        return [];
    }

    /**
     * Resolve the constructor argument into a flat settings array.
     *
     * The string (file) case is delegated to {@see SettingsLoader::file()}
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
    private function loadDefaults(): array
    {
        if (is_array($this->config)) {
            if (SettingsLoader::hasIntegerKey($this->config)) {
                throw new \InvalidArgumentException(
                    'Typography settings array must be a map of option name to value, not a sequence.',
                );
            }

            return $this->config;
        }

        if ($this->config === '') {
            return [];
        }

        return SettingsLoader::file($this->config);
    }
}
