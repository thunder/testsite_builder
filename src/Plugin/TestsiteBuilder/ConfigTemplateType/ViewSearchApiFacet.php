<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;

/**
 * View Search API facet config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_search_api_facet",
 *   label = @Translation("View Search API facet plugin"),
 *   description = @Translation("View Search API facet plugin config template type plugin.")
 * )
 */
class ViewSearchApiFacet extends Generic {

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    $facet_source_id_parts = explode('__', $source_field_config['facet_source_id']);
    $view_page = end($facet_source_id_parts);

    $config_replacement = [
      'name' => "Label: {$field_name}",
      'url_alias' => $field_name,
      'field_identifier' => $field_name,
      'facet_source_id' => "search_api:views_page__{$collection_id}_{$entity_type}_{$bundle}__{$view_page}",
    ];

    return new ConfigTemplateMerge(ConfigTemplateMerge::UPDATE_VALUE, $config_replacement);
  }

}
