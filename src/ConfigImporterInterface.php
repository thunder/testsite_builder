<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Sampler plugins.
 */
interface ConfigImporterInterface extends PluginInspectionInterface {

  /**
   * Imports missing configuration with required altering.
   *
   * @param string $original
   *   The original configuration name that requires missing configuration.
   * @param string $missing
   *   The missing configuration name.
   */
  public function importConfig(string $original, string $missing);

}
