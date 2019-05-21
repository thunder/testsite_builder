<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\EntityType;

/**
 * Counts base fields of entities.
 *
 * @EntityType(
 *   id = "media",
 *   label = @Translation("Entity type base")
 * )
 */
class Media extends EntityTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getBundleConfig($bundle_id, array $bundle_config) {
    $config = parent::getBundleConfig($bundle_id, $bundle_config);
    $config['source'] = $bundle_config['source'];

    return $config;
  }

}
