<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View style columns config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_style_info",
 *   label = @Translation("View style info"),
 *   description = @Translation("View style info config template type plugin.")
 * )
 */
class ViewStyleInfo extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, $source_field_config, $field_name);
  }

}
