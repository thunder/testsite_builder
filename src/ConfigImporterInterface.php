<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for config import plugin.
 */
interface ConfigImporterInterface extends PluginInspectionInterface {

  /**
   * Imports missing configuration with required altering.
   *
   * @param string $dependent
   *   The dependent configuration name that requires missing configuration.
   * @param string $missing
   *   The missing configuration name.
   */
  public function importConfig(string $dependent, string $missing);

}
