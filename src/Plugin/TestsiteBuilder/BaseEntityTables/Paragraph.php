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
    foreach (['paragraphs_item_field_data', 'paragraphs_item_revision_field_data'] as $table_name) {
      if (isset($row_template[$table_name])) {
        $row_template[$table_name]['parent_id'] = $entity_state_info['parent_id'];
        $row_template[$table_name]['parent_type'] = $entity_state_info['parent_type'];
        $row_template[$table_name]['parent_field_name'] = $entity_state_info['parent_field_name'];
      }
    }
  }

}
