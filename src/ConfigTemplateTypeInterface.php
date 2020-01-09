<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for config template type plugin.
 */
interface ConfigTemplateTypeInterface extends PluginInspectionInterface {

  /**
   * Generates field specific configurations for config type.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   * @param string $source_field_name
   *   The mapping source field.
   * @param array $source_definition
   *   The generate definition information.
   *
   * @return array
   *   Returns configuration specific for config type and new field name.
   */
  public function getConfigForField(string $entity_type, string $field_name, string $source_field_name, array $source_definition);

  /**
   * Generates bundle specific configurations for config type.
   *
   * @param string $bundle
   *   The bundle name.
   * @param array|string $source_definition
   *   The generate definition information.
   *
   * @return array
   *   Returns configuration specific for config type and new field name.
   */
  public function getConfigForBundle(string $bundle, $source_definition);

}
