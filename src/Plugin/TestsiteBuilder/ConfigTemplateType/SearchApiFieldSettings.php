<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * Search API Field Settings config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "search_api_field_settings",
 *   label = @Translation("Search API Field Settings"),
 *   description = @Translation("Search API Field Settings config template type plugin.")
 * )
 */
class SearchApiFieldSettings extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    if (empty($source_field_config)) {
      return parent::getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
    }

    $source_field_config['label'] = "Label: {$field_name}";
    $source_field_config['datasource_id'] = "entity:{$entity_type}";
    $source_field_config['property_path'] = $field_name;
    $source_field_config['dependencies'] = [
      'config' => [
        "field.storage.node.{$field_name}",
      ],
    ];

    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, $source_field_config, $field_name);
  }

}
