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
   * Sample data storage.
   *
   * @var array
   */
  protected $sampleDataStorage = [];

  protected $inSubProcess = FALSE;


  public function setInSubProcess () {
    $this->inSubProcess = TRUE;
  }

  public function inSubprocess() {
    return $this->inSubProcess;
  }

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
   * Returns full content creator configuration.
   *
   * @return array
   *   The config creator configuration.
   */
  public function getConfig() {
    return $this->configStorage;
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
   * Add sample data for data type.
   *
   * @param string $type
   *   The data type.
   * @param array $sample_data
   *   The sample data.
   */
  public function addSampleData($type, array $sample_data) {
    $this->sampleDataStorage[$type] = $sample_data;
  }

  /**
   * Checks if sample data ty[e already exists.
   *
   * @param string $type
   *   The data type.
   *
   * @return bool
   *   Returns if sample data for data type already exists.
   */
  public function hasSampleData($type) {
    return isset($this->sampleDataStorage[$type]);
  }

  /**
   * Returns sample data.
   *
   * @return array
   *   The sample data.
   */
  public function getSampleData() {
    return $this->sampleDataStorage;
  }

  /**
   * The output file to store JSON of sample data types.
   *
   * @param string $file_name
   *   The output file name.
   */
  public function storeSampleDataToFile($file_name) {
    file_put_contents($file_name, json_encode($this->sampleDataStorage, JSON_PRETTY_PRINT));
  }

  /**
   * Get sample data used to generate database entries.
   *
   * TODO: We call this a lot. Investigate if performance can be improved.
   *
   * @param string $type
   *   The data type.
   * @param bool $random
   *   Flag if random value should be returned or not.
   *
   * @return mixed
   *   Returns sample data.
   */
  public function getSampleDataType($type, $random = TRUE) {
    if (!isset($this->sampleDataStorage[$type])) {
      return [];
    }

    if (!$random) {
      return $this->sampleDataStorage[$type][0];
    }

    return $this->sampleDataStorage[$type][rand(0, count($this->sampleDataStorage[$type]) - 1)];
  }

  /**
   * Get sample data used to generate database entries with mod of provided ID.
   *
   * @param string $type
   *   The data type.
   * @param int $id
   *   The ID of entry we are fetching sample data for.
   *
   * @return mixed
   *   Returns sample data.
   */
  public function getModSampleDataType($type, $id) {
    if (!isset($this->sampleDataStorage[$type])) {
      return [];
    }

    return $this->sampleDataStorage[$type][$id % count($this->sampleDataStorage[$type])];
  }

}
