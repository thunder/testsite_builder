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
  public function getConfigChangesForBundle(string $collection_id, string $entity_type, string $bundle, $source_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::CHANGE_VALUE, "admin/{$collection_id}_{$entity_type}_{$bundle}/{$entity_type}");
  }

}
