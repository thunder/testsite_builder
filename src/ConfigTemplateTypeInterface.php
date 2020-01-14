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
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param mixed $source_field_config
   *   The configuration from source field or custom configuration.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateMerge
   *   Returns configuration template merge for config type and new field name.
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config);

  /**
   * Generates bundle specific configurations for config type.
   *
   * @param string $bundle
   *   The bundle name.
   * @param mixed $source_config
   *   The source configuration or single value.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateMerge
   *   Returns configuration template merge for config type and bundle.
   */
  public function getConfigChangesForBundle(string $bundle, $source_config);

}
