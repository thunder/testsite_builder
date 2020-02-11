<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigTemplateType;

use Drupal\testsite_builder\ConfigTemplateMerge;
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
   * The config template type manager service.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateTypePluginManager
   */
  protected $configTemplateTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->configTemplateTypeManager = $container->get('testsite_builder.config_template_type_manager');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigChangesForField(string $entity_type, string $bundle, string $field_name, $source_field_config) {
    if (empty($source_field_config)) {
      return parent::getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
    }

    /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $view_field_plugin_config_template_type */
    $view_field_plugin_config_template_type = $this->configTemplateTypeManager->createInstance('view_field_plugin_' . $source_field_config['plugin_id']);
    $config_template_merge = $view_field_plugin_config_template_type->getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);

    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type);

    $source_field_config['id'] = $field_name;
    $source_field_config['field'] = $field_name;
    $source_field_config['label'] = "Label: {$field_name}";

    // TODO: find solution.
    $source_field_config['table'] = $storage->getTableMapping()->getFieldTableName($field_name);
    $source_field_config['entity_field'] = $field_name;

    // Apply custom change related to view filter plugin.
    $source_field_config = $config_template_merge->applyMerge($source_field_config, []);

    return new ConfigTemplateMerge(ConfigTemplateMerge::ADD_KEY, $source_field_config, $field_name);
  }

}
