<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines an interface for EntityType plugins.
 */
interface EntityTypeInterface extends PluginInspectionInterface {

  /**
   * Creates a bundle entity.
   *
   * @param string $bundle_id
   *   The id of the new bundle.
   * @param array $bundle_config
   *   Additional bundle config.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityBundleBase
   *   A new bundle entity.
   */
  public function createBundle(string $bundle_id, array $bundle_config): ConfigEntityBundleBase;

  /**
   * Determines if we can create a bundle like this.
   *
   * @param array $bundle_config
   *   Additional bundle config.
   *
   * @return bool
   *   TRUE if we can create a bundle, otherwise FALSE.
   */
  public function isApplicable(array $bundle_config) : bool;

  /**
   * Method to do post bundle creation operations.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase $bundle
   *   The new bundle.
   * @param array $bundle_config
   *   Additional bundle config.
   */
  public function postCreate(ConfigEntityBundleBase $bundle, array $bundle_config) : void;

}
