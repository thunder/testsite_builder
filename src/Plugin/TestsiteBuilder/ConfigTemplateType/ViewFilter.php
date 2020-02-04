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
   * The config template type manager service.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateTypePluginManager
   */
  protected $configTemplateTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->viewsData = $container->get('views.views_data');
    $plugin->entityFieldManager = $container->get('entity_field.manager');
    $plugin->configTemplateTypeManager = $container->get('testsite_builder.config_template_type_manager');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    if (empty($source_field_config)) {
      return parent::getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
    }

    /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $view_filter_plugin_config_template_type */
    $view_filter_plugin_config_template_type = $this->configTemplateTypeManager->createInstance('view_filter_plugin_' . $source_field_config['plugin_id']);
    $config_template_merge = $view_filter_plugin_config_template_type->getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);

    // Apply custom change related to view filter plugin.
    $source_field_config = $config_template_merge->applyMerge($source_field_config, []);

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
  public function getPossibleFieldSourceConfigKeys(FieldDefinitionInterface $field_definition, string $field_name = ''): array {
    $field_storage = $field_definition->getFieldStorageDefinition();
    $field_type_columns = array_keys($field_storage->getColumns());
    $config_keys_for_field = parent::getPossibleFieldSourceConfigKeys($field_definition);

    $view_filter_keys_for_field = [];
    // Append possible columns for all field names.
    foreach ($config_keys_for_field as $config_key_for_field) {
      foreach ($field_type_columns as $field_type_column) {
        $view_filter_keys_for_field[] = "{$config_key_for_field}_{$field_type_column}";
      }

      $view_filter_keys_for_field[] = $config_key_for_field;
    }

    return $view_filter_keys_for_field;
  }

}
