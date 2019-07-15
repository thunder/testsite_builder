<?php

namespace Drupal\testsite_builder\Plugin\BaseEntityTables;

/**
 * Taxonomy term entity type plugin.
 *
 * @BaseEntityTables(
 *   id = "taxonomy_term",
 *   label = @Translation("TaxonomyTerm"),
 *   description = @Translation("Base entity tables plugin for taxonomy_term.")
 * )
 */
class TaxonomyTerm extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getBaseTableTemplates() {
    $row_templates = parent::getBaseTableTemplates();

    $row_templates['taxonomy_term__parent'] = [
      'vid' => '',
      'deleted' => 0,
      'tid' => 0,
      'revision_id' => 0,
      'langcode' => 'en',
      'delta' => 0,
      'parent_target_id' => 0,
    ];

    $row_templates['taxonomy_term_revision__parent'] = [
      'vid' => '',
      'deleted' => 0,
      'tid' => 0,
      'revision_id' => 0,
      'langcode' => 'en',
      'delta' => 0,
      'parent_target_id' => 0,
    ];

    return $row_templates;
  }

}
