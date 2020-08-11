<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\testsite_builder\ConfigTemplateMerge;
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
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::SKIP);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForBundle(string $collection_id, string $entity_type, string $bundle, $source_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::SKIP);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleFieldSourceConfigKeys(FieldDefinitionInterface $field_definition, string $field_name = ''): array {
    $field_storage = $field_definition->getFieldStorageDefinition();

    $result = [];
    // Create fallback field names.
    if (empty($field_name)) {
      if ($field_storage->isBaseField()) {
        $result[] = $field_definition->getName();
      }

      if ($field_storage->getType() === 'entity_reference') {
        $target_type = $field_storage->getSetting('target_type');
        $result[] = "field_{$field_definition->getType()}__{$target_type}";
      }

      $result[] = "field_{$field_definition->getType()}";
    }
    else {
      $result[] = $field_name;
    }

    return $result;
  }

}
