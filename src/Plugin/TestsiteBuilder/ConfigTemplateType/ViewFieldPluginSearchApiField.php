<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View field plugin Search API field config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_field_plugin_search_api_field",
 *   label = @Translation("View field plugin Search API field"),
 *   description = @Translation("View field plugin Search API field config template type plugin.")
 * )
 */
class ViewFieldPluginSearchApiField extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, "search_api_index_{$collection_id}_{$entity_type}_{$bundle}", 'table');
  }

}
