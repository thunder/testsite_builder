<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Utility\NestedArray;

/**
 * Config template merge.
 *
 * @package Drupal\testsite_builder
 */
class ConfigTemplateMerge {

  /**
   * Enum of merge tactics.
   */
  const SKIP = 0;
  const ADD_VALUE = 1;
  const ADD_KEY = 2;
  const CHANGE_VALUE = 3;
  const UPDATE_VALUE = 4;

  /**
   * The merge tactic.
   *
   * @var string
   */
  protected $tactic;

  /**
   * The configuration key.
   *
   * @var string
   */
  protected $key;

  /**
   * The data for config template merge.
   *
   * It's usually string or array.
   *
   * @var mixed
   */
  protected $data;

  /**
   * The config template merge constructor.
   *
   * @param string $tactic
   *   The tactic key.
   * @param string $data
   *   The configuration data.
   * @param string $key
   *   The configuration key.
   */
  public function __construct($tactic, $data = '', $key = '') {
    $this->tactic = $tactic;
    $this->data = $data;
    $this->key = $key;
  }

  /**
   * Applies template configuration merge.
   *
   * @param array $config
   *   The base configuration.
   * @param array $path
   *   The configuration merge path.
   *
   * @return array
   *   Returns configuration with applied merge changes.
   */
  public function applyMerge(array $config, array $path) {
    if ($this->tactic === static::SKIP) {
      return $config;
    }

    if ($this->tactic === static::ADD_VALUE) {
      $array_value = NestedArray::getValue($config, $path);
      $array_value[] = $this->data;
      NestedArray::setValue($config, $path, $array_value);

      return $config;
    }

    if ($this->tactic === static::ADD_KEY) {
      $path[] = $this->key;
      NestedArray::setValue($config, $path, $this->data);
    }

    if ($this->tactic === static::CHANGE_VALUE) {
      NestedArray::setValue($config, $path, $this->data);
    }

    if ($this->tactic === static::UPDATE_VALUE) {
      $array_value = NestedArray::getValue($config, $path);
      $new_config = NestedArray::mergeDeep($array_value, $this->data);
      NestedArray::setValue($config, $path, $new_config);
    }

    return $config;
  }

  /**
   * Sets config template merge tactic data.
   *
   * @param mixed $data
   *   The data for merge tactic.
   */
  public function setData($data) {
    $this->data = $data;
  }

  /**
   * Gets config template merge tactic data.
   *
   * @return mixed
   *   Returns data for config merge tactic.
   */
  public function getData() {
    return $this->data;
  }

}
