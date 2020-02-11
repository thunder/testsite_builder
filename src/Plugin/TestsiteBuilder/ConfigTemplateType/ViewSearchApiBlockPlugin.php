<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View Search API block plugin config template type plugin.
 *
 * We have to convert something like this:
 * 'views_exposed_filter_block:content_search_api-page_1'
 * Into this:
 * 'views_exposed_filter_block:content_search_api_bundle_0-page_1'
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_search_api_block_plugin",
 *   label = @Translation("View Search API block plugin"),
 *   description = @Translation("View Search API block plugin config template type plugin.")
 * )
 */
class ViewSearchApiBlockPlugin extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForBundle(string $bundle, $source_config) {
    $exposed_filter_plugin = explode('-', $source_config);
    $exposed_filter_plugin[0] .= "_{$bundle}";

    return new ConfigTemplateMerge(ConfigTemplateMerge::CHANGE_VALUE, implode('-', $exposed_filter_plugin));
  }

}
