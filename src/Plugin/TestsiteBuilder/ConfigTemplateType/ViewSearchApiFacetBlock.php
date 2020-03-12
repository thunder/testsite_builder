<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View Search API facet block config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_search_api_facet_block",
 *   label = @Translation("View Search API facet block plugin"),
 *   description = @Translation("View Search API facet block plugin config template type plugin.")
 * )
 */
class ViewSearchApiFacetBlock extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    $config_replacement = [
      'plugin' => "facet_block:{$collection_id}_{$entity_type}_{$bundle}_{$field_name}",
      'settings' => [
        'id' => "facet_block:{$collection_id}_{$entity_type}_{$bundle}_{$field_name}",
        'label' => "Label: {$field_name}",
        'block_id' => "{$collection_id}_{$entity_type}_{$bundle}_{$field_name}",
      ],
    ];

    return new ConfigTemplateMerge(ConfigTemplateMerge::UPDATE_VALUE, $config_replacement);
  }

}
