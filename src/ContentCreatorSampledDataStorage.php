<?php

namespace Drupal\testsite_builder;

/**
 * Stores sampled data for data types.
 *
 * @package Drupal\testsite_builder
 */
class ContentCreatorSampledDataStorage {

  protected $sampledData = [];

  /**
   * Add sampled data for data type.
   *
   * @param string $type
   *   The data type.
   * @param array $sampled_data
   *   The sampled data.
   */
  public function addData($type, array $sampled_data) {
    $this->sampledData[$type] = $sampled_data;
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
  public function hasType($type) {
    return isset($this->sampledData[$type]);
  }

  /**
   * The output file to store JSON of sampled data types.
   *
   * @param string $file_name
   *   The output file name.
   */
  public function storeSampledDataToFile($file_name) {
    file_put_contents($file_name, json_encode($this->sampledData, JSON_PRETTY_PRINT));
  }

}
