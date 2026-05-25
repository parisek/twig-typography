<?php

declare(strict_types=1);

namespace Parisek\Twig;

use PHP_Typography\PHP_Typography;
use PHP_Typography\Settings;
use Symfony\Component\Yaml\Yaml;
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
     * @param string|array<string, mixed> $config
     *   - string ''       → use the bundled marker `typography.yml` (library defaults).
     *   - string '/path'  → load the YAML file at the given absolute path.
     *   - array  []|[...] → use the array as settings directly; no filesystem I/O.
     *
     * A non-existent file path falls back silently to library defaults, preserving
     * the 1.1.x behaviour that consumers (notably parisek/styleguide) rely on
     * when their `typography_config` key resolves to a path the project hasn't
     * created yet.
     */
    public function __construct(string|array $config = '')
    {
        $this->config = $config;
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
     * @param string|\Stringable    $string        Plain string or any Stringable (Twig\Markup from `|raw`-wrapped HTML, value objects with __toString, …). Cast happens at entry so the rest of the method works on a plain string.
     * @param array<string, mixed>  $arguments     Per-call setting overrides; merged on top of constructor defaults.
     * @param bool                  $use_defaults  Initialise PHP-Typography's own sane defaults before applying ours.
     */
    public function applyTypography(string|\Stringable $string, array $arguments = [], bool $use_defaults = true): string
    {
        $string = (string) $string;

        $settings = new Settings($use_defaults);

        $merged = array_merge($this->loadDefaults(), $arguments);
        foreach ($merged as $setting => $value) {
            $settings->{$setting}($value);
        }

        return (new PHP_Typography())->process($string, $settings);
    }

    /**
     * Resolve the constructor argument into a flat settings array.
     *
     * @return array<string, mixed>
     */
    private function loadDefaults(): array
    {
        if (is_array($this->config)) {
            return $this->config;
        }

        $path = $this->config !== '' ? $this->config : __DIR__ . '/../typography.yml';

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $parsed = Yaml::parse($contents);

        return is_array($parsed) ? $parsed : [];
    }
}
