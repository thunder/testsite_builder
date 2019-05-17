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
  protected $map;

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
    if (!empty($this->map[serialize($field_storage_config)][$bundle])) {
      return NULL;
    }
    if (!empty($this->map[serialize($field_storage_config)])) {
      return current($this->map[serialize($field_storage_config)]);
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
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');
    $defs = $fieldManager->getFieldMapByFieldType($field_storage_config['type']);
    $defs = array_filter($defs[$field_storage_config['entity_type']], function ($key) use ($field_storage_config) {
      return (strpos($key, $field_storage_config['type']) === 0);
    }, ARRAY_FILTER_USE_KEY);

    return $field_storage_config['type'] . '_' . count($defs);
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
    unset($field_storage_config['field_name']);
    $this->map[serialize($field_storage_config)][$bundle] = $fieldStorageConfig;
  }

}
