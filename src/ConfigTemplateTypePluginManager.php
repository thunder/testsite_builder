<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the config template type plugin manager.
 */
class ConfigTemplateTypePluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Constructs a new ConfigTemplateTypePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/TestsiteBuilder/ConfigTemplateType', $namespaces, $module_handler, 'Drupal\testsite_builder\ConfigTemplateTypeInterface', 'Drupal\testsite_builder\Annotation\TestsiteBuilderConfigTemplateType');

    $this->alterInfo('testsite_builder_config_template_type_plugin_info');
    $this->setCacheBackend($cache_backend, 'testsite_builder_config_template_type_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'generic';
  }

}
