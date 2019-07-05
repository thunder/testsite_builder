<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\field\FieldConfigInterface;

/**
 * Defines an interface for FieldType plugins.
 */
interface FieldTypeInterface extends PluginInspectionInterface {

  /**
   * Creates all fields of a specific type.
   */
  public function createField() : FieldConfigInterface;

  /**
   * Determines if we can add a field like this.
   *
   * @return bool
   *   TRUE if we can add a new field, otherwise FALSE.
   */
  public function isApplicable() : bool;

}
