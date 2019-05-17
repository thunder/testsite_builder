<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

use Drupal\field\FieldStorageConfigInterface;

/**
 * Counts base fields of entities.
 *
 * @FieldType(
 *   id = "entity_reference_revisions",
 *   label = @Translation("Field type base")
 * )
 */
class EntityReferenceRevisions extends EntityReference {

  /**
   * {@inheritdoc}
   */
  protected function getFieldConfig($instance, FieldStorageConfigInterface $fieldStorage) {
    $config = parent::getFieldConfig($instance, $fieldStorage);
    $config['settings']['handler'] = 'default:paragraph';
    $config['settings']['handler_settings']['target_bundles_drag_drop'] = array_combine($instance['target_bundles'], array_fill(0, count($instance['target_bundles']), ['enabled' => 1]));
    return $config;
  }

}
