<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, FieldTypePluginManagerInterface $fieldTypePluginManager, CreatedFieldManager $createdFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
    $this->createdFieldManager = $createdFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : FieldTypeInterface {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('plugin.manager.field.field_type'), $container->get('testsite_builder.created_field_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function createFields() : void {
    $form_display = entity_get_form_display($this->configuration['entity_type'], $this->configuration['bundle_type'], 'default');
    foreach ($this->configuration['instances'] as $instance) {

      if (!$this->isApplicable($instance)) {
        continue;
      }

      $field_storage_config = $this->getFieldStorageConfig($instance);
      if (!($field_storage = $this->createdFieldManager->getFieldStorage($field_storage_config, $this->configuration['bundle_type']))) {
        $field_storage_config['field_name'] = $this->createdFieldManager->getFieldStorageName($field_storage_config, $this->configuration['bundle_type']);
        /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
        $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->create($field_storage_config);
        $field_storage->save();
      }
      unset($field_storage_config['field_name']);
      $this->createdFieldManager->addFieldStorage($field_storage_config, $this->configuration['bundle_type'], $field_storage);

      /** @var \Drupal\field\FieldConfigInterface $field_instance */
      $field_instance = $this->entityTypeManager->getStorage('field_config')->create($this->getFieldConfig($instance, $field_storage));
      $field_instance->save();

      $form_display->setComponent($field_instance->getName(), $this->getFieldWidgetConfig());
    }
    $form_display->save();
  }

  /**
   * Determines if we can add a field like this.
   *
   * @param array $instance
   *   Array of instance settings.
   *
   * @return bool
   *   TRUE if we can add a new field, otherwise FALSE.
   */
  protected function isApplicable(array $instance) : bool {
    $fieldTypeDefinitions = $this->fieldTypePluginManager->getDefinitions();
    if (!isset($fieldTypeDefinitions[$this->configuration['field_type']])) {
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
      'entity_type' => $this->configuration['entity_type'],
      'type' => $this->configuration['field_type'],
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
      'type' => $this->configuration['field_type'],
      'bundle' => $this->configuration['bundle_type'],
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
    $fieldTypeDefinitions = $this->fieldTypePluginManager->getDefinitions();
    return ['type' => $fieldTypeDefinitions[$this->configuration['field_type']]['default_widget']];
  }

}
