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

}
