<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for FieldType plugins.
 */
interface FieldTypeInterface extends PluginInspectionInterface {

  /**
   * Creates all fields of a specific type.
   */
  public function createFields() : void;

}
