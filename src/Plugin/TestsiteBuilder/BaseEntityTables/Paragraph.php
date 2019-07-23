<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\BaseEntityTables;

/**
 * Paragraph entity type plugin.
 *
 * @TestsiteBuilderBaseEntityTables(
 *   id = "paragraph",
 *   label = @Translation("Paragraph"),
 *   description = @Translation("Base entity tables plugin for paragraphs.")
 * )
 */
class Paragraph extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getBaseTableTemplates() {
    $row_templates = parent::getBaseTableTemplates();

    return $row_templates;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRowTemplate(array &$row_template, array $entity_state_info) {
    if (isset($row_template['paragraphs_item_field_data'])) {
      $row_template['paragraphs_item_field_data']['parent_id'] = $entity_state_info['parent_id'];
      $row_template['paragraphs_item_field_data']['parent_type'] = $entity_state_info['parent_type'];
      $row_template['paragraphs_item_field_data']['parent_field_name'] = $entity_state_info['parent_field_name'];
    }
  }

}
