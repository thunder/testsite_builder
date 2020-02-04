<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View bundle path config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_bundle_path",
 *   label = @Translation("View bundle path"),
 *   description = @Translation("View bundle path config template type plugin.")
 * )
 */
class ViewBundlePath extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForBundle(string $bundle, $source_config) {
    $path = explode('/', $source_config);
    $path_size = count($path);

    for ($i = 1; $i < $path_size; $i++) {
      $path[$i] .= "_{$bundle}";
    }

    return new ConfigTemplateMerge(ConfigTemplateMerge::CHANGE_VALUE, implode('/', $path));
  }

}
