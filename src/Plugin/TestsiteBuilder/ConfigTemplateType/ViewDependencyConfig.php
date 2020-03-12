<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View dependency config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_dependency_config",
 *   label = @Translation("View dependency config"),
 *   description = @Translation("View dependency config template type plugin.")
 * )
 */
class ViewDependencyConfig extends Generic {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $collection_id, string $entity_type, string $bundle, string $field_name, $source_field_config) {
    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);
    if (strpos($storage->getTableMapping()->getFieldTableName($field_name), "{$entity_type}__") === 0) {
      return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_VALUE, "field.storage.{$entity_type}.{$field_name}");
    }

    return parent::getConfigChangesForField($collection_id, $entity_type, $bundle, $field_name, $source_field_config);
  }

}
