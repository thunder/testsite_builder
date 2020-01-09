<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

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
  public function getConfigForField(string $entity_type, string $field_name, string $source_field_name, array $source_definition) {
    return [$field_name, $field_name];
  }

}
