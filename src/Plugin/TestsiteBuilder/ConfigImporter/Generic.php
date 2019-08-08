<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigImporter;

use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\testsite_builder\ConfigImporterInterface;
use Drupal\update_helper\ConfigName;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic config importer plugin.
 *
 * @TestsiteBuilderConfigImporter(
 *   id = "generic",
 *   label = @Translation("Generic"),
 *   description = @Translation("Generic config importer plugin.")
 * )
 */
class Generic extends PluginBase implements ConfigImporterInterface, ContainerFactoryPluginInterface {

  /**
   * The config reverter service.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigRevertInterface $config_reverter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configReverter = $config_reverter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('config_update.config_update'));
  }

  /**
   * {@inheritdoc}
   */
  public function importConfig(string $original, string $missing) {
    $missing_config_name = ConfigName::createByFullName($missing);

    $this->configReverter->import($missing_config_name->getType(), $missing_config_name->getName());
  }

}
