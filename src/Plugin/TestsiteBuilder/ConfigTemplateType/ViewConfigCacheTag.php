<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View config cache tag config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_config_cache_tag",
 *   label = @Translation("View config cache tag"),
 *   description = @Translation("View config cache tag config template type plugin.")
 * )
 */
class ViewConfigCacheTag extends Generic {

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
  public function getConfigForField(string $entity_type, string $field_name, string $source_field_name, array $source_definition) {
    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);
    if (strpos($storage->getTableMapping()->getFieldTableName($field_name), '__')) {
      return ['', "config:field.storage.{$entity_type}.{$field_name}"];
    }

    return parent::getConfigForField($entity_type, $field_name, $source_field_name, $source_definition);
  }

}
