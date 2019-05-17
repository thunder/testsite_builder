<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

use Drupal\field\FieldStorageConfigInterface;

/**
 * EntityReferenceRevisions field type.
 *
 * @FieldType(
 *   id = "entity_reference_revisions",
 *   label = @Translation("EntityReferenceRevisions")
 * )
 */
class EntityReferenceRevisions extends EntityReference {

  /**
   * {@inheritdoc}
   */
  protected function getFieldConfig($instance, FieldStorageConfigInterface $fieldStorage) : array {
    $config = parent::getFieldConfig($instance, $fieldStorage);
    $config['settings']['handler'] = 'default:paragraph';
    $config['settings']['handler_settings']['target_bundles_drag_drop'] = array_combine($instance['target_bundles'], array_fill(0, count($instance['target_bundles']), ['enabled' => 1]));
    return $config;
  }

}
