<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\testsite_builder\Exception\FieldTypeNotSupportedException;

/**
 * Provides the entity data plugin manager.
 */
class FieldTypePluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * The field type plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * Constructs a new FieldTypePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, FieldTypePluginManagerInterface $fieldTypePluginManager) {
    parent::__construct('Plugin/testsite_builder/FieldType', $namespaces, $module_handler, 'Drupal\testsite_builder\FieldTypeInterface', 'Drupal\testsite_builder\Annotation\FieldType');

    $this->alterInfo('testsite_builder_field_type_info');
    $this->setCacheBackend($cache_backend, 'testsite_builder_field_types');
    $this->fieldTypePluginManager = $fieldTypePluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    $fieldTypeDefinitions = $this->fieldTypePluginManager->getDefinitions();
    if (!isset($fieldTypeDefinitions[$configuration['field_type']])) {
      throw new FieldTypeNotSupportedException($configuration['field_type']);
    }
    return 'field_type_base';
  }

}
