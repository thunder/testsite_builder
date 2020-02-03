<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Config template importer.
 *
 * @package Drupal\testsite_builder
 */
class ConfigTemplateImporter {

  /**
   * The config template type manager service.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateTypePluginManager
   */
  protected $configTemplateTypeManager;

  /**
   * The config template definition.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateDefinition
   */
  protected $templateDefinition;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Constructs a new ConfigTemplateImporter object.
   *
   * @param \Drupal\testsite_builder\ConfigTemplateTypePluginManager $config_template_type_manager
   *   The config template type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager service.
   */
  public function __construct(ConfigTemplateTypePluginManager $config_template_type_manager, ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $bundle_info, EntityFieldManagerInterface $field_manager) {
    $this->configTemplateTypeManager = $config_template_type_manager;
    $this->configFactory = $config_factory;
    $this->bundleInfo = $bundle_info;
    $this->fieldManager = $field_manager;
  }

  /**
   * Copy configuration parts from source to destination config.
   *
   * In case of part of array is copied, it will merge arrays.
   *
   * @param array $config
   *   The destination configuration.
   * @param string|array $path_from
   *   The source path as string or parent array.
   * @param string|array $path_to
   *   The destination path as string or parent array.
   *
   * @return mixed
   *   Returns destination configuration with copied parts.
   */
  protected function copyFromSource(array $config, $path_from, $path_to) {
    $source_config = $this->templateDefinition->getSourceConfig();

    if (is_string($path_from)) {
      $path_from = explode('.', $path_from);
    }

    if (is_string($path_to)) {
      $path_to = explode('.', $path_to);
    }

    $source_value = NestedArray::getValue($source_config, $path_from);
    $destination_value = NestedArray::getValue($config, $path_to);

    if (!empty($destination_value) && is_array($destination_value)) {
      $source_value = NestedArray::mergeDeepArray([
        $destination_value,
        $source_value,
      ]);
    }

    NestedArray::setValue($config, $path_to, $source_value);

    return $config;
  }

  /**
   * Generate configuration for bundle based on existing template.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle ID.
   * @param array $dynamic_field_mapping_definitions
   *   The dynamic field mapping definitions.
   * @param array $dynamic_view_definitions
   *   The dynamic view definitions.
   *
   * @return array
   *   Returns new generated configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createConfigForBundle($entity_type, $bundle, array $dynamic_field_mapping_definitions, array $dynamic_view_definitions) {
    $config = $this->templateDefinition->getCleanConfig();

    // Copy pre generate.
    foreach ($this->templateDefinition->getKey('pre_generate_clone') as $copy_config_key) {
      $config = $this->copyFromSource($config, $copy_config_key['from'], $copy_config_key['to']);
    }

    // Generate dynamic configuration for fields.
    $config = $this->applyConfigurationForFields($entity_type, $bundle, $config, $dynamic_field_mapping_definitions);

    // Generate dynamic configuration for bundle.
    $config = $this->applyConfigurationForBundle($bundle, $config, $dynamic_view_definitions);

    // Copy post generate.
    foreach ($this->templateDefinition->getKey('post_generate_clone') as $copy_config_key) {
      $config = $this->copyFromSource($config, $copy_config_key['from'], $copy_config_key['to']);
    }

    // Create ID for config.
    $source_config = $this->templateDefinition->getSourceConfig();
    $config['id'] = "{$source_config['id']}_{$bundle}";

    return $config;
  }

  /**
   * Add dynamic configurations for field.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle ID.
   * @param array $config
   *   The configuration.
   * @param array $dynamic_field_mapping_definitions
   *   The dynamic field mapping definitions from template file.
   *
   * @return array
   *   Returns configuration with new changes.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function applyConfigurationForFields($entity_type, $bundle, array $config, array $dynamic_field_mapping_definitions) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $bundle_fields */
    $bundle_fields = $this->fieldManager->getFieldDefinitions($entity_type, $bundle);
    foreach ($bundle_fields as $field_name => $field_definition) {
      // Skip computed fields.
      if ($field_definition->isComputed()) {
        continue;
      }

      // Get field mapping if available, otherwise skip field.
      $field_map = $this->templateDefinition->findFieldMapping($field_definition);
      if ($field_map === FALSE) {
        continue;
      }

      foreach ($dynamic_field_mapping_definitions as $dynamic_field_definition) {
        // Check exclude field definition types.
        if (isset($field_map['exclude_generation_type']) && in_array($dynamic_field_definition['type'], $field_map['exclude_generation_type'])) {
          continue;
        }

        /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
        $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($dynamic_field_definition['type']);

        // Use configuration from source config or fallback.
        $source_field_config = $this->templateDefinition->getDynamicSourceDefinitionForField(
          $dynamic_field_definition['path'],
          empty($field_map['source_field']) ? '' : $field_map['source_field'],
          empty($field_map['fallback_field']) ? $config_template_type_plugin->getPossibleFieldSourceConfigKeys($field_definition) : [$field_map['fallback_field']]
        );

        /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
        $config_template_merge = $config_template_type_plugin->getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
        $config = $config_template_merge->applyMerge($config, $dynamic_field_definition['path']);
      }
    }

    return $config;
  }

  /**
   * Applies dynamic configuration for bundle.
   *
   * @param string $bundle
   *   The bundle name.
   * @param array $config
   *   The configuration.
   * @param array $dynamic_view_definitions
   *   The dynamic definitions from template file for bundle.
   *
   * @return array
   *   Returns configuration with new changes.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function applyConfigurationForBundle($bundle, array $config, array $dynamic_view_definitions) {
    foreach ($dynamic_view_definitions as $dynamic_view_definition) {
      $source_view_config = $this->templateDefinition->getDynamicSourceDefinition($dynamic_view_definition['path']);

      /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
      $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($dynamic_view_definition['type']);

      /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
      $config_template_merge = $config_template_type_plugin->getConfigChangesForBundle($bundle, $source_view_config);
      $config = $config_template_merge->applyMerge($config, $dynamic_view_definition['path']);
    }

    return $config;
  }

  /**
   * Load template configuration from yaml file.
   *
   * @param string $file_name
   *   The file name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function loadTemplate($file_name) {
    // 1. load template mapping definition.
    $this->templateDefinition = ConfigTemplateDefinition::createFromFile($file_name);

    // 2. prepare mapping information for fields and cleanup config.
    $dynamic_field_mapping_definitions = $this->templateDefinition->getDynamicFieldDefinitions();
    $dynamic_view_definitions = $this->templateDefinition->getDynamicViewDefinitions();

    // 3. create configuration for bundles.
    $entity_type = $this->templateDefinition->getKey('entity_type');
    $bundles = array_keys($this->bundleInfo->getBundleInfo($entity_type));
    foreach ($bundles as $bundle) {
      $bundle_config = $this->createConfigForBundle($entity_type, $bundle, $dynamic_field_mapping_definitions, $dynamic_view_definitions);

      $this->configFactory->getEditable($this->templateDefinition->getKey('source') . "_{$bundle}")
        ->setData($bundle_config)
        ->save(TRUE);
    }
  }

}
