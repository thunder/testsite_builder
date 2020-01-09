<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

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
  public function getConfigForBundle(string $bundle, $source_definition) {
    $path = explode('/', $source_definition);

    for ($i = 1; $i < count($path); $i++) {
      $path[$i] .= "_{$bundle}";
    }

    return ['', implode('/', $path)];
  }

}
