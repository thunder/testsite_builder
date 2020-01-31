<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\testsite_builder\ConfigTemplateMerge;
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->viewsData = $container->get('views.views_data');
    $plugin->entityFieldManager = $container->get('entity_field.manager');

    return $plugin;
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
    $source_field_config['entity_type'] = $entity_type;
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

  /**
   * {@inheritdoc}
   */
  public function getPossibleFieldSourceConfigKeys(FieldDefinitionInterface $field_definition): array {
    $field_storage = $field_definition->getFieldStorageDefinition();
    $field_type_columns = array_keys($field_storage->getColumns());

    $result = parent::getPossibleFieldSourceConfigKeys($field_definition);
    foreach ($field_type_columns as $field_type_column) {
      if ($field_storage->getType() === 'entity_reference') {
        $target_type = $field_storage->getSetting('target_type');
        $result[] = "field_{$field_storage->getType()}__{$target_type}_{$field_type_column}";
      }

      $result[] = "field_{$field_storage->getType()}_{$field_type_column}";
    }

    return $result;
  }

}
