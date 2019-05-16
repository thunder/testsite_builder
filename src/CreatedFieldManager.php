<?php

namespace Drupal\testsite_builder;

use Drupal\field\FieldStorageConfigInterface;

class CreatedFieldManager {

  protected $map;

  /**
   *
   *
   * @param array $field_storage_config
   *   The field storage config.
   * @param string $bundle
   *   The bundle the field should belong to.
   *
   * @return \Drupal\field\FieldStorageConfigInterface
   */
  public function getFieldStorage(array $field_storage_config, $bundle) {
    if (!empty($this->map[serialize($field_storage_config)][$bundle])) {
      return NULL;
    }
    if (!empty($this->map[serialize($field_storage_config)])) {
      return current($this->map[serialize($field_storage_config)]);
    }
    return NULL;
  }

  public function getFieldStorageName(array $field_storage_config) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager */
    $fieldManager = \Drupal::service('entity_field.manager');
    $defs = $fieldManager->getFieldMapByFieldType($field_storage_config['type']);
    $defs = array_filter($defs[$field_storage_config['entity_type']], function ($key) use ($field_storage_config) {
      return (strpos($key, $field_storage_config['type']) === 0);
    }, ARRAY_FILTER_USE_KEY);

    return $field_storage_config['type'] . '_' . count($defs);
  }

  public function addFieldStorage(array $field_storage_config, $bundle, FieldStorageConfigInterface $fieldStorageConfig) {
    unset($field_storage_config['field_name']);
    $this->map[serialize($field_storage_config)][$bundle] = $fieldStorageConfig;
  }

}
