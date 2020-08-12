<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View Search API table config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_search_api_table",
 *   label = @Translation("View Search API table"),
 *   description = @Translation("View Search API table config template type plugin.")
 * )
 */
class ViewSearchApiTable extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForBundle(string $collection_id, string $entity_type, string $bundle, $source_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::CHANGE_VALUE, "search_api_index_{$collection_id}_{$entity_type}_{$bundle}");
  }

}
