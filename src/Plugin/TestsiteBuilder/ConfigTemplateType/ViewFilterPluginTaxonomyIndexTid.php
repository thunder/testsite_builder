<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View filter plugin Taxonomy Index TID config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_filter_plugin_taxonomy_index_tid",
 *   label = @Translation("View filter plugin Taxonomy Index TID"),
 *   description = @Translation("View filter plugin Taxonomy Index TID config template type plugin.")
 * )
 */
class ViewFilterPluginTaxonomyIndexTid extends Generic {

  /**
   * Then entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityFieldManager = $container->get('entity_field.manager');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    if (empty($source_field_config)) {
      return parent::getConfigChangesForField($collection_id, $entity_type, $bundle, $field_name, $source_field_config);
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

    if (empty($field_definitions[$field_name])) {
      return parent::getConfigChangesForField($collection_id, $entity_type, $bundle, $field_name, $source_field_config);
    }

    $field_settings = $field_definitions[$field_name]->getSettings();
    if (empty($field_settings['handler_settings']['target_bundles'])) {
      return parent::getConfigChangesForField($collection_id, $entity_type, $bundle, $field_name, $source_field_config);
    }

    reset($field_settings['handler_settings']['target_bundles']);

    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, key($field_settings['handler_settings']['target_bundles']), 'vid');
  }

}
