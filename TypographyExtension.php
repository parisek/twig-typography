<?php

namespace Parisek\Twig;

use PHP_Typography\Settings;
use PHP_Typography\PHP_Typography;
use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TypographyExtension extends AbstractExtension {

  protected $settings_file_path = __DIR__ . '/typography.yml';

  public function __construct($settings_file_path = '') {
    if(!empty($settings_file_path)) {
      $this->settings_file_path = $settings_file_path;
    }
  }

  public function getFilters() {
    return [
      new TwigFilter('typography', [
        $this,
        'applyTypography',
      ], [
        'is_safe' => [
          'html'
        ]
      ]),
    ];
  }

  /**
   * Runs the PHP-Typography.
   *
   * @param string $string
   *   The string of text to apply the filter to.
   * @param array $arguments
   *   An optional array containing the settings for the typography library.
   *   This should be set as a hash (key value pair) in twig template.
   * @param bool $use_defaults
   *   - TRUE: a sane set of defaults are loaded.
   *   - FALSE: settings will need to be passed in and no defaults will
   *     be applied.
   *
   * @return string
   *   A processed and filtered string to return to the template.
   *
   * @throws \Exception
   *   An exception is thrown if a string is not passed.
   */
  public function applyTypography($string, array $arguments = [], $use_defaults = TRUE) {
    $settings = new Settings($use_defaults);
    // Load the defaults from the theme and merge them with any
    // supplied arguments from the calling function in the template.
    $arguments = array_merge(self::getDefaults(), $arguments);
    // Process the arguments and add them to the settings object.
    foreach ($arguments as $setting => $value) {
      $settings->{$setting}($value);
    }
    $typo = new PHP_Typography();

    // Process the string with any provided arguments (and/or defaults) and
    // return it.
    $string = $typo->process($string, $settings);
    return $string;
  }

  /**
   * Gets defaults from a YAML file if it exists.
   *
   * @return array
   *   A set of defaults loaded from a YAML file if found.
   */
  private function getDefaults() {
    $defaults = [];
    if (file_exists($this->settings_file_path)) {
      $defaults = (array) Yaml::parse(file_get_contents($this->settings_file_path));
    }
    dump($defaults);
    return $defaults;
  }
}
