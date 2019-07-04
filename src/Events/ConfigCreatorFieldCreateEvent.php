<?php

namespace Drupal\testsite_builder\Events;

use Drupal\field\FieldConfigInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Event for config creator field create.
 *
 * @package Drupal\testsite_builder\Events
 */
class ConfigCreatorFieldCreateEvent extends GenericEvent {

  /**
   * The field configuration.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $fieldConfig;

  /**
   * The filed configuration provided by Sampler module.
   *
   * @var array
   */
  protected $samplerFieldConfig;

  /**
   * ConfigCreatorEntityCreateEvent constructor.
   *
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The field configuration.
   * @param array $sampler_field_config
   *   The filed configuration provided by Sampler module.
   */
  public function __construct(FieldConfigInterface $field_config, array $sampler_field_config) {
    $this->fieldConfig = $field_config;
    $this->samplerFieldConfig = $sampler_field_config;
  }

  /**
   * Get event field configuration.
   *
   * @return \Drupal\field\FieldConfigInterface
   *   The field configuration.
   */
  public function getFieldConfig() {
    return $this->fieldConfig;
  }

  /**
   * Get field configuration provided by Sampler.
   *
   * @return array
   *   Returns field configuration.
   */
  public function getSamplerFieldConfig() {
    return $this->samplerFieldConfig;
  }

}
