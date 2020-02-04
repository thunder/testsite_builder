<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View style column config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_style_columns",
 *   label = @Translation("View style column"),
 *   description = @Translation("View style column config template type plugin.")
 * )
 */
class ViewStyleColumn extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, $field_name, $field_name);
  }

}
