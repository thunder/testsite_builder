<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View field plugin Search API field config template type plugin.
 *
 * @TestsiteBuilderConfigTemplateType(
 *   id = "view_field_plugin_search_api_field",
 *   label = @Translation("View field plugin Search API field"),
 *   description = @Translation("View field plugin Search API field config template type plugin.")
 * )
 */
class ViewFieldPluginSearchApiField extends Generic {

  /**
   * Then entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityFieldManager = $container->get('entity_field.manager');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, "search_api_index_content_{$bundle}", 'table');
  }

}
