<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

use Drupal\Component\Plugin\PluginBase;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\testsite_builder\CreatedFieldManager;
use Drupal\testsite_builder\FieldTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Counts base fields of entities.
 *
 * @FieldType(
 *   id = "field_type_base",
 *   label = @Translation("Field type base")
 * )
 */
class FieldTypeBase extends PluginBase implements FieldTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field type plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * The created field manager service.
   *
   * @var \Drupal\testsite_builder\CreatedFieldManager
   */
  protected $createdFieldManager;

  /**
   * The config reverter service.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

  /**
   * Stores the widget mapping.
   *
   * @var array
   */
  protected $widgetMapping = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, FieldTypePluginManagerInterface $fieldTypePluginManager, CreatedFieldManager $createdFieldManager, ConfigRevertInterface $configReverter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
    $this->createdFieldManager = $createdFieldManager;
    $this->configReverter = $configReverter;

    $module_path = drupal_get_path('module', 'testsite_builder');
    $this->widgetMapping = Yaml::decode(file_get_contents($module_path . DIRECTORY_SEPARATOR . 'widget_mapping.yml'));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : FieldTypeInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('testsite_builder.created_field_manager'),
      $container->get('config_update.config_update')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createField() : FieldConfigInterface {
    $form_display = entity_get_form_display($this->configuration['entity_type'], $this->configuration['bundle_type'], 'default');

    $field_storage_config = $this->getFieldStorageConfig($this->configuration);
    if (!($field_storage = $this->createdFieldManager->getFieldStorage($field_storage_config, $this->configuration['bundle_type']))) {
      /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
      $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->create($field_storage_config);
      $field_storage->save();
    }
    $this->createdFieldManager->addFieldStorage($field_storage_config, $this->configuration['bundle_type'], $field_storage);

    /** @var \Drupal\field\FieldConfigInterface $field_instance */
    $field_instance = $this->entityTypeManager->getStorage('field_config')
      ->create($this->getFieldConfig($this->configuration, $field_storage));
    $field_instance->save();

    $form_display->setComponent($field_instance->getName(), $this->getFieldWidgetConfig());
    $form_display->save();

    return $field_instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable() : bool {
    $fieldTypeDefinitions = $this->fieldTypePluginManager->getDefinitions();
    if (!isset($fieldTypeDefinitions[$this->configuration['type']])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Returns the field storage configuration.
   *
   * @param array $instance
   *   Array of instance settings.
   *
   * @return array
   *   Default field storage config.
   */
  protected function getFieldStorageConfig(array $instance) : array {
    return [
      'field_name' => $instance['field_name'],
      'entity_type' => $this->configuration['entity_type'],
      'type' => $this->configuration['type'],
      'cardinality' => $instance['cardinality'],
      'settings' => [],
    ];
  }

  /**
   * Returns the field widget configuration.
   *
   * @param array $instance
   *   Array of instance settings.
   * @param \Drupal\field\FieldStorageConfigInterface $fieldStorage
   *   The storage where the new instance belongs to.
   *
   * @return array
   *   Default field config.
   */
  protected function getFieldConfig(array $instance, FieldStorageConfigInterface $fieldStorage) : array {
    return [
      'field_name' => $fieldStorage->getName(),
      'entity_type' => $this->configuration['entity_type'],
      'type' => $this->configuration['type'],
      'bundle' => $this->configuration['bundle_type'],
      'required' => $this->configuration['required'] ?? FALSE,
      'settings' => [],
    ];
  }

  /**
   * Returns the field widget configuration.
   *
   * @return array
   *   Default field widget config.
   */
  protected function getFieldWidgetConfig() : array {
    if (!empty($this->widgetMapping[$this->configuration['type']])) {
      foreach ($this->widgetMapping[$this->configuration['type']] as $mapping) {
        $match = TRUE;
        foreach ($mapping['conditions'] as $key => $value) {
          if (!isset($this->configuration[$key]) || $this->configuration[$key] != $value) {
            $match = FALSE;
          }
        }

        if ($match) {
          $config = $mapping['config'];
          return $this->getWidget($config['entity_type'], $config['bundle'], $config['view_mode'], $config['field']);
        }
      }
    }

    $fieldTypeDefinitions = $this->fieldTypePluginManager->getDefinitions();
    return ['type' => $fieldTypeDefinitions[$this->configuration['type']]['default_widget']];
  }

  /**
   * Read widget setting from config file.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $form_mode
   *   The form mode.
   * @param string $field_name
   *   The field name.
   *
   * @return array|null
   *   The widget config array.
   */
  protected function getWidget(string $entity_type, string $bundle, string $form_mode, string $field_name) : ?array {
    $display = $this->configReverter->getFromExtension('entity_form_display', sprintf('%s.%s.%s', $entity_type, $bundle, $form_mode));

    return $display['content'][$field_name] ?? NULL;
  }

}
