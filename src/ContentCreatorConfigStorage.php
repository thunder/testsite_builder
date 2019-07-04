<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Utility\NestedArray;

/**
 * Stores configuration for content creator.
 *
 * @package Drupal\testsite_builder
 */
class ContentCreatorConfigStorage {

  protected $contentCreatorConfig = [];

  /**
   * Add config.
   *
   * @param array $path
   *   The configuration path.
   * @param array $config
   *   The configuration for provided path.
   */
  public function addConfig(array $path, array $config) {
    $config_to_merge = [];
    NestedArray::setValue($config_to_merge, $path, $config);

    $this->contentCreatorConfig = NestedArray::mergeDeep($this->contentCreatorConfig, $config_to_merge);
  }

  /**
   * Checks if configuration already exists.
   *
   * @param array $path
   *   The configuration path.
   *
   * @return bool
   *   Returns if config is already set.
   */
  public function hasConfig(array $path) {
    return NestedArray::keyExists($this->contentCreatorConfig, $path);
  }

  /**
   * The output file to store JSON of content creator configuration.
   *
   * @param string $file_name
   *   The output file name.
   */
  public function storeConfigToFile($file_name) {
    file_put_contents($file_name, json_encode($this->contentCreatorConfig, JSON_PRETTY_PRINT));
  }

}
