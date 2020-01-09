<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View field config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_field",
 *   label = @Translation("View field"),
 *   description = @Translation("View field config template type plugin.")
 * )
 */
class ViewField extends Generic {

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
    $field_definition = $source_definition[$source_field_name];
    if (empty($field_definition)) {
      return parent::getConfigForField($entity_type, $field_name, $source_field_name, $source_definition);
    }

    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);

    $field_definition['id'] = $field_name;
    $field_definition['table'] = $storage->getTableMapping()->getFieldTableName($field_name);
    $field_definition['field'] = $field_name;
    $field_definition['entity_field'] = $field_name;
    $field_definition['label'] = "Label: {$field_name}";

    return [
      $field_name,
      $field_definition,
    ];
  }

}
