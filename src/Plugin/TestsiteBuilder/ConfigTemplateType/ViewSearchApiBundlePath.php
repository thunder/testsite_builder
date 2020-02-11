<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View Search API bundle path config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_search_api_bundle_path",
 *   label = @Translation("View Search API bundle path"),
 *   description = @Translation("View Search API bundle path config template type plugin.")
 * )
 */
class ViewSearchApiBundlePath extends ViewBundlePath {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForBundle(string $bundle, $source_config) {
    $path = array_values(array_filter(explode('/', $source_config)));
    $path_size = count($path);

    for ($i = 1; $i < $path_size; $i++) {
      $path[$i] .= "_{$bundle}_search_api";
    }

    return new ConfigTemplateMerge(ConfigTemplateMerge::CHANGE_VALUE, implode('/', $path));
  }

}
