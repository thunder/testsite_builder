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
  public function getConfigChangesForBundle(string $bundle, $source_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, "{$source_config['base_table']}_{$bundle}", 'base_table');
  }

}
