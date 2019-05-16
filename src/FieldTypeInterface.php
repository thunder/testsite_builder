<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Sampler plugins.
 */
interface FieldTypeInterface extends PluginInspectionInterface {

  /**
   * @return mixed
   */
  public function createFields();

}
