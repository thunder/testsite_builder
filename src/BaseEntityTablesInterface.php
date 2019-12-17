<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Sampler plugins.
 */
interface BaseEntityTablesInterface extends PluginInspectionInterface {

  /**
   * The key of the data in the result.
   *
   * @return array
   *   Returns list of base table templates for entity.
   */
  public function getBaseTableTemplates();

  /**
   * Returns custom columns for entity plugin.
   *
   * @param array $row_template
   *   Reference to row template.
   * @param array $entity_state_info
   *   Entity state.
   */
  public function alterRowTemplate(array &$row_template, array $entity_state_info);

  /**
   * Alter row data.
   *
   * @param array $row
   *   Reference to row.
   * @param string $table_name
   *   The table name of the row.
   * @param \Drupal\testsite_builder\ContentCreatorStorage $content_creator_storage
   *   The content creator storage.
   */
  public function alterRow(array &$row, $table_name, ContentCreatorStorage $content_creator_storage);

}
