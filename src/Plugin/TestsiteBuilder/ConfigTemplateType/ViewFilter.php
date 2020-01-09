<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View filter config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_filter",
 *   label = @Translation("View filter"),
 *   description = @Translation("View filter config template type plugin.")
 * )
 */
class ViewFilter extends Generic {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The views data service.
   *
   * @var \Drupal\views\ViewsData
   */
  protected $viewsData;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ViewsData $views_data) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->viewsData = $views_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('views.views_data'));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForField(string $entity_type, string $field_name, string $source_field_name, array $source_definition) {
    $filter_definition = $source_definition[$source_field_name];
    if (empty($filter_definition)) {
      return parent::getConfigForField($entity_type, $field_name, $source_field_name, $source_definition);
    }

    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);

    $filter_definition['id'] = $field_name;
    $filter_definition['table'] = $storage->getTableMapping()
      ->getFieldTableName($field_name);
    $filter_definition['field'] = $field_name;
    $filter_definition['entity_field'] = $field_name;
    $filter_definition['expose']['label'] = "Filter: {$field_name}";
    $filter_definition['expose']['identifier'] = $field_name;
    $filter_definition['expose']['operator_id'] = "{$field_name}_op";
    $filter_definition['expose']['operator'] = "{$field_name}_op";
    $filter_definition['group_info']['label'] = "Filter: {$field_name}";
    $filter_definition['group_info']['identifier'] = $field_name;

    // Handling of non-base fields.
    if (strpos($filter_definition['table'], '__')) {
      $views_data = $this->viewsData->get($filter_definition['table']);
      $filter_definition['field'] = $views_data[$field_name]['field']['real field'];

      unset($filter_definition['entity_type']);
      unset($filter_definition['entity_field']);
    }

    return [
      $field_name,
      $filter_definition,
    ];
  }

}
