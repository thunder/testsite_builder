<?php

namespace Drupal\testsite_builder\Events;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for config creator entity create.
 *
 * @package Drupal\testsite_builder\Events
 */
class ConfigCreatorEntityBundleCreateEvent extends Event {

  /**
   * The entity.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $bundleConfig;

  /**
   * The bundle configuration provided by Sampler module.
   *
   * @var array
   */
  protected $samplerBundleConfig;

  /**
   * ConfigCreatorEntityCreateEvent constructor.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_config
   *   The entity bundle.
   * @param array $sampler_bundle_config
   *   The bundle configuration provided by Sampler module.
   */
  public function __construct(ConfigEntityInterface $bundle_config, array $sampler_bundle_config) {
    $this->bundleConfig = $bundle_config;
    $this->samplerBundleConfig = $sampler_bundle_config;
  }

  /**
   * Get event entity.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The entity.
   */
  public function getBundleConfig() {
    return $this->bundleConfig;
  }

  /**
   * Get bundle configuration provided by Sampler.
   *
   * @return array
   *   Returns bundle configuration.
   */
  public function getSamplerBundleConfig() {
    return $this->samplerBundleConfig;
  }

}
