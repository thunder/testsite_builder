<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

use Drupal\field\FieldStorageConfigInterface;

/**
 * Counts base fields of entities.
 *
 * @FieldType(
 *   id = "entity_reference",
 *   label = @Translation("Field type base")
 * )
 */
class EntityReference extends FieldTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldStorageConfig($instance) {
    $config = parent::getFieldStorageConfig($instance);
    $config['settings']['target_type'] = $instance['target_type'];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldConfig($instance, FieldStorageConfigInterface $fieldStorage) {
    $config = parent::getFieldConfig($instance, $fieldStorage);
    $config['settings']['handler_settings']['target_bundles'] = $instance['target_bundles'];
    return $config;
  }

}
