<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\testsite_builder\ConfigTemplateTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "generic",
 *   label = @Translation("Generic"),
 *   description = @Translation("Generic config template type plugin.")
 * )
 */
class Generic extends PluginBase implements ConfigTemplateTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForField(string $entity_type, string $field_name, string $source_field_name, array $source_definition) {
    return ['', ''];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForBundle(string $bundle, $source_definition) {
    return ['', ''];
  }

}
