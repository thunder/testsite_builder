<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

/**
 * View Search API exposed form block path config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_search_api_exposed_form_block_path",
 *   label = @Translation("View Search API exposed form block path"),
 *   description = @Translation("View Search API exposed form block path config template type plugin.")
 * )
 */
class ViewSearchApiExposedFormBlockPath extends ViewBundlePath {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForBundle(string $collection_id, string $entity_type, string $bundle, $source_config) {
    $template = parent::getConfigChangesForBundle($collection_id, $entity_type, $bundle, $source_config);
    $template->setData('/' . $template->getData());

    return $template;
  }

}
