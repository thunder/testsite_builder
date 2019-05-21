<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the entity data plugin manager.
 */
class EntityTypePluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Constructs a new EntityTypePluginManager object.
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
    parent::__construct('Plugin/testsite_builder/EntityType', $namespaces, $module_handler, 'Drupal\testsite_builder\EntityTypeInterface', 'Drupal\testsite_builder\Annotation\EntityType');

    $this->alterInfo('testsite_builder_entity_type_info');
    $this->setCacheBackend($cache_backend, 'testsite_builder_entitiy_types');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'entity_type_base';
  }

}
