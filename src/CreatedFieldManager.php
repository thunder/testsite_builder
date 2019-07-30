<?php

namespace Drupal\testsite_builder;

use Drupal\field\FieldStorageConfigInterface;

/**
 * Manages the created fields.
 */
class CreatedFieldManager {

  /**
   * A map the store all field configs.
   *
   * @var array
   */
  protected $createFields;

  /**
   * Returns an existing field storage when not used in the given bundle so far.
   *
   * @param array $field_storage_config
   *   The field storage config.
   * @param string $bundle
   *   The bundle the field should belong to.
   *
   * @return \Drupal\field\FieldStorageConfigInterface
   *   The field storage config.
   */
  public function getFieldStorage(array $field_storage_config, string $bundle) : ?FieldStorageConfigInterface {
    $field_name = $field_storage_config['field_name'];
    $entity_type = $field_storage_config['entity_type'];

    if (!empty($this->createFields[$entity_type][$field_name][$bundle])) {
      return NULL;
    }

    if (!empty($this->createFields[$entity_type][$field_name])) {
      return current($this->createFields[$entity_type][$field_name]);
    }

    return NULL;
  }

  /**
   * Adds a field to the map.
   *
   * @param array $field_storage_config
   *   The field storage config.
   * @param string $bundle
   *   The bundle the field should belong to.
   * @param \Drupal\field\FieldStorageConfigInterface $fieldStorageConfig
   *   The field storage config object.
   */
  public function addFieldStorage(array $field_storage_config, $bundle, FieldStorageConfigInterface $fieldStorageConfig) : void {
    $this->createFields[$field_storage_config['entity_type']][$field_storage_config['field_name']][$bundle] = $fieldStorageConfig;
  }

}
