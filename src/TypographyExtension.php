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
     * Settings objects already built for this instance, keyed by everything
     * that can change one: the locale candidates, `$use_defaults`, and the
     * per-call `$arguments`.
     *
     * Per instance rather than static, because `$config` belongs to the
     * instance. Two extensions constructed with different project settings
     * must not answer each other's cache, and keying on the config itself
     * would mean serializing an arbitrary array on every call to save
     * building one object.
     *
     * @var array<string, Settings>
     */
    private array $settingsCache = [];

    /**
     * Upper bound on {@see $settingsCache}.
     *
     * The cache is keyed partly by caller-supplied `$arguments`, and nothing
     * stops a call site from passing a value that differs every call — a
     * threshold, an id, anything JSON-encodable and call-unique. In a request
     * that would not matter; in a persistent worker, which is the environment
     * {@see flushCaches()} exists for, it grows for the life of the process.
     *
     * Measured on the project this change came from: 358 call sites, every one
     * of them argument-free, so the live cache holds one entry per locale. The
     * bound is for the consumer that does not look like that.
     *
     * The whole cache is dropped on overflow rather than evicting least-used
     * entries, because tracking use costs a write on every hit — on the hot
     * path this cache exists to protect. A rebuild is what the uncached code
     * did on every call anyway.
     */
    private const MAX_CACHED_SETTINGS = 100;

    /**
     * The generation this instance's cache was filled in.
     *
     * A flush is process-wide in its effects — it drops the shared processor
     * and the parsed-file memo, which every instance reads — so it has to be
     * process-wide in its reach too. Without this, flushing one instance left
     * every other instance answering from settings built out of the parse that
     * was just thrown away: cleared for the flusher, stale for its siblings,
     * and no way for a caller holding one instance to reach the others.
     *
     * A counter rather than a registry of instances, so nothing has to hold a
     * reference to an extension that would otherwise be collectable.
     */
    private int $settingsCacheGeneration = 0;

    /** Incremented by every {@see flushCaches()} call, for every instance. */
    private static int $generation = 0;

    /**
     * The processor, shared by every instance in the process.
     *
     * Static because it holds no settings — `process()` takes them per call —
     * and because the thing worth keeping is its lazily built fix registry.
     * Two extension instances with different project config can share one
     * processor safely, and building the registry twice would waste the
     * saving on a host that registers more than one.
     */
    private static ?PHP_Typography $typography = null;

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

        if ($this->settingsCacheGeneration !== self::$generation) {
            $this->settingsCache = [];
            $this->settingsCacheGeneration = self::$generation;
        }

        $candidates = $this->localeCandidates();
        $cacheKey = $this->settingsCacheKey($candidates, $use_defaults, $arguments);

        if ($cacheKey !== null && isset($this->settingsCache[$cacheKey])) {
            return $this->process($string, $this->settingsCache[$cacheKey]);
        }

        $settings = new Settings($use_defaults);

        $packagePath = SettingsLoader::packagePath();

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
            //
            // method_exists() alone is not enough: it returns true for
            // protected/private methods too (e.g. `get_style` is a real,
            // protected Settings method — calling it from here fatals with
            // "Call to protected method"). is_callable() with this exact
            // syntax ([$settings, $setting]) resolves visibility from the
            // *calling scope* (this file, not inside the Settings class), so
            // it only returns true for methods actually invocable from here
            // — i.e. public ones. That is exactly the guard we need, with no
            // extra Reflection object to construct.
            //
            // Additionally require a `set_` prefix. Every meaningful
            // Settings option is exposed as a `set_*` mutator; the class also
            // exposes public non-`set_` methods (getters, `__construct`,
            // etc.) that must never be reachable via a settings key. Without
            // the prefix check, a magic method like `__construct` or a
            // future public getter would either fatal (wrong arity) or
            // silently corrupt state instead of being skipped. Requiring the
            // prefix also makes the contract legible: a settings key maps to
            // a setter, full stop. The one tradeoff — a hypothetical future
            // public setter that doesn't follow the `set_` convention would
            // be silently skipped rather than applied — is the safer failure
            // mode for a library call we don't control upstream (fail silent,
            // not fail fatal).
            if (!str_starts_with($setting, 'set_') || !is_callable([$settings, $setting])) {
                continue;
            }

            $settings->{$setting}($value);
        }

        if ($cacheKey !== null) {
            if (count($this->settingsCache) >= self::MAX_CACHED_SETTINGS) {
                $this->settingsCache = [];
            }

            $this->settingsCache[$cacheKey] = $settings;
        }

        return $this->process($string, $settings);
    }

    /**
     * The cache key for one call, or null when the call cannot be keyed.
     *
     * `$arguments` is part of the key because it is part of the answer. It can
     * also hold a closure — `PARSER_ERRORS_HANDLER` is documented as callable —
     * and a closure has no stable serialization, so such a call is not cached
     * rather than sharing a key with a different closure. Rare and correct:
     * the alternative is two calls that differ only in their handler quietly
     * getting the same settings.
     *
     * @param  array<int, string>   $candidates
     * @param  array<string, mixed> $arguments
     */
    private function settingsCacheKey(array $candidates, bool $useDefaults, array $arguments): ?string
    {
        if (!self::isKeyable($arguments)) {
            return null;
        }

        // Sorted so that two calls passing the same options in a different
        // order share an entry. Ordering never changes the answer -- the
        // setters are applied independently -- so treating it as significant
        // would only cost a rebuild.
        self::sortRecursive($arguments);

        try {
            return md5(json_encode([$candidates, $useDefaults, $arguments], JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Whether every value can be told apart by `json_encode()`.
     *
     * Asked explicitly, because `json_encode()` does not complain about the
     * values that matter here: a Closure encodes as `{}` and so does any other
     * object without `JsonSerializable`. Two different `PARSER_ERRORS_HANDLER`
     * closures therefore produce an identical key, and the second call is
     * served the first call's settings — carrying the first call's handler,
     * which then runs on the second call's errors.
     *
     * An earlier version of this method relied on `JSON_THROW_ON_ERROR` to
     * refuse such a call. It never fired, and the test written alongside it
     * asserted the two calls were the same — passing because of the defect it
     * was meant to exclude.
     *
     * @param array<mixed> $values
     */
    private static function isKeyable(array $values): bool
    {
        foreach ($values as $value) {
            if (is_object($value) || is_resource($value)) {
                return false;
            }

            if (is_array($value) && !self::isKeyable($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sort an array by key, at every depth.
     *
     * @param array<mixed> $values
     */
    private static function sortRecursive(array &$values): void
    {
        foreach ($values as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }
        unset($value);

        ksort($values);
    }

    /**
     * Hand one string and its settings to the upstream processor.
     *
     * The processor is reused across calls. mundschenk-at/php-typography builds
     * that for reuse — `get_registry()` caches the registry on the instance and
     * `process()` takes the settings per call — so constructing one per filter
     * invocation threw the cache away every time. On a page that filters 1727
     * strings that was 1727 registry builds.
     *
     * Settings are treated as read-only during processing: every reference to
     * them inside the upstream class is a read. That is what makes one settings
     * object safe to serve many strings.
     *
     * mundschenk-at/php-typography's latest release is v6.7.0 (Nov 2022),
     * predating PHP 8.4. Its method signatures still use implicitly-nullable
     * parameters (e.g. `callable $handler = null`), which PHP 8.4+ deprecates.
     * With display_errors on, those E_DEPRECATED notices are written straight
     * into the output stream and corrupt the rendered HTML. Suppress only
     * E_DEPRECATED for the duration of the upstream call, then restore the
     * previous level so genuine errors elsewhere are unaffected.
     *
     * This is purely a stopgap for the unmaintained 2022 dependency — drop it
     * once php-typography ships the nullable type-hint fix and we bump to it:
     * https://github.com/mundschenk-at/php-typography/pull/189
     */
    private function process(string $string, Settings $settings): string
    {
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED);

        try {
            // `??=` is a check-then-act on process-wide state and is not
            // atomic. That is deliberate rather than overlooked: under ZTS a
            // static is per-thread, and where requests interleave as
            // coroutines rather than threads — Swoole and friends — two of
            // them can both see null and both construct one. The loser's
            // instance is simply dropped. The cost is a wasted registry build,
            // never a wrong answer, because the processor carries no settings.
            return (self::$typography ??= new PHP_Typography())->process($string, $settings);
        } finally {
            error_reporting($previousErrorReporting);
        }
    }

    /**
     * Drop everything cached for this instance, and the shared processor.
     *
     * A web request never needs it: the process ends before a settings file or
     * a language table can change. A long-running one does — WP-CLI and
     * persistent workers run many units of work in one process, and tests need
     * one test's cache not to answer the next one's question.
     */
    public function flushCaches(): void
    {
        $this->settingsCache = [];
        self::$typography = null;
        ++self::$generation;
        $this->settingsCacheGeneration = self::$generation;

        // The parsed files too, or this clears the settings objects and
        // rebuilds them from the same stale parse.
        SettingsLoader::flushMemo();
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
     * The merged `languages:` entries for every candidate tag present in the
     * settings file at $path, folded least-specific to most-specific.
     *
     * `$candidates` arrives most-specific-first (e.g. `['en-GB', 'en']`), so
     * this walks it in reverse and layers each present entry on top of the
     * last: `en` first, then `en-GB` merged over it. A regional entry is
     * therefore additive over its base language, exactly like every other
     * layer in the merge order — it only needs to state the keys that
     * genuinely differ, and a key it doesn't restate still comes from the
     * bare-language entry beneath it. An earlier "first tag present wins"
     * version of this method silently dropped the base language's settings
     * whenever a regional entry existed at all (e.g. `en-GB` losing `en`'s
     * `set_smart_ordinal_suffix`), which contradicted the documented
     * "additive, merged per key" contract for `languages:`.
     *
     * @param  array<int, string>   $candidates
     * @return array<string, mixed>
     */
    private function resolveLanguageSettings(string $path, array $candidates): array
    {
        $merged = [];
        foreach (array_reverse($candidates) as $tag) {
            $merged = array_merge($merged, SettingsLoader::language($path, $tag));
        }

        return $merged;
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
            $merged = [];
            foreach (array_reverse($candidates) as $tag) {
                $merged = array_merge($merged, SettingsLoader::extractLanguage($this->config, $tag));
            }

            return $merged;
        }

        if ($this->config === '') {
            return [];
        }

        return $this->resolveLanguageSettings($this->config, $candidates);
    }
}
