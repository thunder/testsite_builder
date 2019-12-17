<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\BaseEntityTables;

use Drupal\testsite_builder\BaseEntityTablesBase;
use Drupal\testsite_builder\ContentCreatorStorage;

/**
 * Generic entity type plugin.
 *
 * @TestsiteBuilderBaseEntityTables(
 *   id = "generic",
 *   label = @Translation("Generic"),
 *   description = @Translation("Generic base entity tables plugin.")
 * )
 */
class Generic extends BaseEntityTablesBase {

  /**
   * {@inheritdoc}
   */
  public function getBaseTableTemplates() {
    $column_mapping_reverse = array_flip($this->configuration['_entity_definition_keys']);

    $row_templates = [];
    foreach ($this->configuration['_base_tables'] as $table_name => $base_table_definition) {
      $row_templates[$table_name] = [];
      foreach ($base_table_definition['_columns'] as $column) {
        if (isset($column_mapping_reverse[$column]) && array_key_exists($column_mapping_reverse[$column], static::$baseTableRowData)) {
          $row_templates[$table_name][$column] = static::$baseTableRowData[$column_mapping_reverse[$column]];

          continue;
        }

        if (isset(static::$baseTableRowData[$column])) {
          $row_templates[$table_name][$column] = static::$baseTableRowData[$column];

          continue;
        }

        $row_templates[$table_name][$column] = NULL;
      }
    }

    return $row_templates;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRowTemplate(array &$row_template, array $entity_state_info) {}

  /**
   * {@inheritdoc}
   */
  public function alterRow(array &$row, $table_name, ContentCreatorStorage $content_creator_storage) {}

}
