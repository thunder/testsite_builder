<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Utility\Crypt;
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
   * Counts the value of field type fields on an entity type.
   *
   * @var int
   */
  protected $fieldCounter;

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
    if (!empty($this->createFields[$this->getHash($field_storage_config)][$bundle])) {
      return NULL;
    }
    if (!empty($this->createFields[$this->getHash($field_storage_config)])) {
      return current($this->createFields[$this->getHash($field_storage_config)]);
    }
    return NULL;
  }

  /**
   * Returns the next free field name for a given field type.
   *
   * @param array $field_storage_config
   *   The field storage config.
   *
   * @return string
   *   The new field name
   */
  public function getFieldStorageName(array $field_storage_config) : string {
    $count = $this->fieldCounter[$field_storage_config['entity_type']][$field_storage_config['type']] ?? 0;
    return $field_storage_config['type'] . '_' . $count;
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
    $this->createFields[$this->getHash($field_storage_config)][$bundle] = $fieldStorageConfig;
    $this->fieldCounter[$field_storage_config['entity_type']][$field_storage_config['type']]++;
  }

  /**
   * Calculates the hash for the map.
   *
   * @param array $field_storage_config
   *   The field storage config.
   *
   * @return string
   *   The hash.
   */
  protected function getHash(array $field_storage_config) : string {
    return Crypt::hashBase64(serialize($field_storage_config));
  }

}
