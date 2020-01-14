<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);
    if (strpos($storage->getTableMapping()->getFieldTableName($field_name), "{$entity_type}__") === 0) {
      return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_VALUE, "field.storage.{$entity_type}.{$field_name}");
    }

    return parent::getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
  }

}
