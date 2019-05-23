<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

use Drupal\field\FieldStorageConfigInterface;

/**
 * EntityReference field type.
 *
 * @FieldType(
 *   id = "entity_reference",
 *   label = @Translation("EntityReference")
 * )
 */
class EntityReference extends FieldTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldStorageConfig($instance) : array {
    $config = parent::getFieldStorageConfig($instance);
    $config['settings']['target_type'] = $instance['target_type'];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldConfig(array $instance, FieldStorageConfigInterface $fieldStorage) : array {
    $config = parent::getFieldConfig($instance, $fieldStorage);
    $config['settings']['handler_settings']['target_bundles'] = array_combine($instance['target_bundles'], $instance['target_bundles']);
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(array $instance) : bool {
    if (!parent::isApplicable($instance)) {
      return FALSE;
    }
    $entity_types = $this->entityTypeManager->getDefinitions();
    if (!isset($entity_types[$instance['target_type']])) {
      return FALSE;
    }
    return TRUE;
  }

}
