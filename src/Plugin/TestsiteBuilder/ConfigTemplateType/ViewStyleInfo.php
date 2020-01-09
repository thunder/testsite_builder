<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

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
  public function getConfigForField(string $entity_type, string $field_name, string $source_field_name, array $source_definition) {
    return [
      $field_name,
      $source_definition[$source_field_name],
    ];
  }

}
