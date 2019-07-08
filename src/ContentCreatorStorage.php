<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Utility\NestedArray;

/**
 * Stores configuration for content creator.
 *
 * @package Drupal\testsite_builder
 */
class ContentCreatorStorage {

  /**
   * Storage for content creator configuration.
   *
   * @var array
   */
  protected $configStorage = [];

  /**
   * Sampled data storage.
   *
   * @var array
   */
  protected $sampledDataStorage = [];

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

    $this->configStorage = NestedArray::mergeDeep($this->configStorage, $config_to_merge);
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
    return NestedArray::keyExists($this->configStorage, $path);
  }

  /**
   * The output file to store JSON of content creator configuration.
   *
   * @param string $file_name
   *   The output file name.
   */
  public function storeConfigToFile($file_name) {
    file_put_contents($file_name, json_encode($this->configStorage, JSON_PRETTY_PRINT));
  }

  /**
   * Add sampled data for data type.
   *
   * @param string $type
   *   The data type.
   * @param array $sampled_data
   *   The sampled data.
   */
  public function addSampledData($type, array $sampled_data) {
    $this->sampledDataStorage[$type] = $sampled_data;
  }

  /**
   * Checks if sampled data ty[e already exists.
   *
   * @param string $type
   *   The data type.
   *
   * @return bool
   *   Returns if sampled data for data type already exists.
   */
  public function hasSampledData($type) {
    return isset($this->sampledDataStorage[$type]);
  }

  /**
   * The output file to store JSON of sampled data types.
   *
   * @param string $file_name
   *   The output file name.
   */
  public function storeSampledDataToFile($file_name) {
    file_put_contents($file_name, json_encode($this->sampledDataStorage, JSON_PRETTY_PRINT));
  }

}
