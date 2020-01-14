<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\testsite_builder\ConfigTemplateMerge;
use Drupal\views\ViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View filter config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_filter",
 *   label = @Translation("View filter"),
 *   description = @Translation("View filter config template type plugin.")
 * )
 */
class ViewFilter extends Generic {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The views data service.
   *
   * @var \Drupal\views\ViewsData
   */
  protected $viewsData;

  /**
   * Then entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ViewsData $views_data, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->viewsData = $views_data;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('views.views_data'), $container->get('entity_field.manager'));
  }

  /**
   * Applies plugin specific configuration.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param mixed $config
   *   The plugin configuration.
   *
   * @return mixed
   *   Returns plugin configuration or FALSE.
   */
  protected function applyPlugConfiguration(string $entity_type, string $bundle, string $field_name, $config) {
    // Handles: "taxonomy_index_tid" - plugin.
    if ($config['plugin_id'] === 'taxonomy_index_tid') {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

      if (empty($field_definitions[$field_name])) {
        return FALSE;
      }

      $field_settings = $field_definitions[$field_name]->getSettings();
      if (empty($field_settings['handler_settings']['target_bundles'])) {
        return FALSE;
      }

      reset($field_settings['handler_settings']['target_bundles']);
      $config['vid'] = key($field_settings['handler_settings']['target_bundles']);
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    if (empty($source_field_config)) {
      return parent::getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
    }

    // Skip configuration if plugin configuration is not changed accordingly.
    $source_field_config = $this->applyPlugConfiguration($entity_type, $bundle, $field_name, $source_field_config);
    if ($source_field_config === FALSE) {
      return parent::getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
    }

    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);

    $source_field_config['id'] = $field_name;
    $source_field_config['table'] = $storage->getTableMapping()
      ->getFieldTableName($field_name);
    $source_field_config['field'] = $field_name;
    $source_field_config['entity_field'] = $field_name;

    $source_field_config['expose']['label'] = "Filter: {$field_name}";
    $source_field_config['expose']['identifier'] = $field_name;
    $source_field_config['expose']['operator_id'] = "{$field_name}_op";
    $source_field_config['expose']['operator'] = "{$field_name}_op";

    $source_field_config['group_info']['label'] = "Filter: {$field_name}";
    $source_field_config['group_info']['identifier'] = $field_name;

    // Handling of non-base fields.
    if (strpos($source_field_config['table'], "{$entity_type}__") === 0) {
      $views_data = $this->viewsData->get($source_field_config['table']);
      $source_field_config['field'] = $views_data[$field_name]['field']['real field'];

      unset($source_field_config['entity_type']);
      unset($source_field_config['entity_field']);
    }

    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, $source_field_config, $field_name);
  }

}
