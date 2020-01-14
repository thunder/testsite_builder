<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\update_helper\ConfigName;

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
   * The yaml serialization service.
   *
   * @var \Drupal\Component\Serialization\Yaml
   */
  protected $yamlSerializer;

  /**
   * The mapping configuration.
   *
   * @var array
   */
  protected $mappingConfig;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config reverter service from config update.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

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
   * @param \Drupal\Component\Serialization\Yaml $yaml_serializer
   *   The yaml serialization service.
   * @param \Drupal\config_update\ConfigRevertInterface $config_reverter
   *   The config reverter service.
   */
  public function __construct(ConfigTemplateTypePluginManager $config_template_type_manager, ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $bundle_info, EntityFieldManagerInterface $field_manager, Yaml $yaml_serializer, ConfigRevertInterface $config_reverter) {
    $this->configTemplateTypeManager = $config_template_type_manager;
    $this->configFactory = $config_factory;
    $this->bundleInfo = $bundle_info;
    $this->fieldManager = $field_manager;
    $this->yamlSerializer = $yaml_serializer;
    $this->configReverter = $config_reverter;
  }

  /**
   * Create copy of source configuration with cleaned dynamic parts.
   *
   * @param array $source_config
   *   The source configuration.
   *
   * @return array
   *   Returns cleaned configuration.
   */
  protected function getCleanConfig(array $source_config) {
    $config = $source_config;
    foreach ($this->mappingConfig['generate_per_field'] as $generate_per_field) {
      NestedArray::setValue($config, explode('.', $generate_per_field['name']), []);
    }

    return $config;
  }

  /**
   * Copy configuration parts from source to destination config.
   *
   * In case of part of array is copied, it will merge arrays.
   *
   * @param array $source_config
   *   The source configuration.
   * @param string|array $path_from
   *   The source path as string or parent array.
   * @param array $destination_config
   *   The destination configuration.
   * @param string|array $path_to
   *   The destination path as string or parent array.
   *
   * @return mixed
   *   Returns destination configuration with copied parts.
   */
  protected function copyFromSource(array $source_config, $path_from, array $destination_config, $path_to) {
    if (is_string($path_from)) {
      $path_from = explode('.', $path_from);
    }

    if (is_string($path_to)) {
      $path_to = explode('.', $path_to);
    }

    $source_value = NestedArray::getValue($source_config, $path_from);
    $destination_value = NestedArray::getValue($destination_config, $path_to);

    if (!empty($destination_value) && is_array($destination_value)) {
      $source_value = NestedArray::mergeDeepArray([$destination_value, $source_value]);
    }

    NestedArray::setValue($destination_config, $path_to, $source_value);

    return $destination_config;
  }

  /**
   * Generate configuration for bundle based on existing template.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle ID.
   * @param array $source_config
   *   The source configuration.
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
  protected function createConfigForBundle($entity_type, $bundle, array $source_config, array $dynamic_field_mapping_definitions, array $dynamic_view_definitions) {
    $config = $this->getCleanConfig($source_config);

    // Copy pre generate.
    foreach ($this->mappingConfig['pre_generate_clone'] as $copy_config_key) {
      $config = $this->copyFromSource($source_config, $copy_config_key['from'], $config, $copy_config_key['to']);
    }

    // Generate dynamic configuration for fields.
    $config = $this->applyConfigurationForFields($entity_type, $bundle, $config, $dynamic_field_mapping_definitions);

    // Generate dynamic configuration for bundle.
    $config = $this->applyConfigurationForBundle($bundle, $config, $dynamic_view_definitions);

    // Copy post generate.
    foreach ($this->mappingConfig['post_generate_clone'] as $copy_config_key) {
      $config = $this->copyFromSource($source_config, $copy_config_key['from'], $config, $copy_config_key['to']);
    }

    // Create ID for config.
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
    $bundle_fields = $this->fieldManager->getFieldDefinitions($this->mappingConfig['entity_type'], $bundle);
    foreach ($bundle_fields as $field_name => $field_definition) {
      // Skip computed fields.
      if ($field_definition->isComputed()) {
        continue;
      }

      // Get field mapping if available, otherwise skip field.
      $field_map = $this->findFieldMapping($field_definition);
      if ($field_map === FALSE) {
        continue;
      }

      foreach ($dynamic_field_mapping_definitions as $dynamic_field_definition) {
        // Check exclude field definition types.
        if (isset($field_map['exclude_generation_type']) && in_array($dynamic_field_definition['type'], $field_map['exclude_generation_type'])) {
          continue;
        }

        // Use custom source_field definition.
        $custom_generation_type_config_template_merge = $this->getCustomTemplateMerge($entity_type, $bundle, $field_name, $dynamic_field_definition['type'], empty($field_map['custom_generation_types']) ? [] : $field_map['custom_generation_types']);
        if ($custom_generation_type_config_template_merge !== FALSE) {
          $config = $custom_generation_type_config_template_merge->applyMerge($config, $dynamic_field_definition['path']);

          continue;
        }

        // Use configuration from source config.
        $source_field_config = (empty($dynamic_field_definition['resource'][$field_map['source_field']])) ? [] : $dynamic_field_definition['resource'][$field_map['source_field']];

        /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
        $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($dynamic_field_definition['type']);

        /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
        $config_template_merge = $config_template_type_plugin->getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
        $config = $config_template_merge->applyMerge($config, $dynamic_field_definition['path']);
      }
    }

    return $config;
  }

  /**
   * Searches for field mapping.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array|bool
   *   Returns field mapping or false if not found.
   */
  protected function findFieldMapping(FieldDefinitionInterface $field_definition) {
    foreach ($this->mappingConfig['field_type_mapping'] as $field_map) {
      if ($this->matchesFieldTypeMapping($field_definition, $field_map)) {
        return $field_map;
      }
    }
    return FALSE;
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
      $source_view_config = (empty($dynamic_view_definition['resource'])) ? [] : $dynamic_view_definition['resource'];

      /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
      $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($dynamic_view_definition['type']);

      /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
      $config_template_merge = $config_template_type_plugin->getConfigChangesForBundle($bundle, $source_view_config);
      $config = $config_template_merge->applyMerge($config, $dynamic_view_definition['path']);
    }

    return $config;
  }

  /**
   * Get template merge for custom config generation type.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param string $generation_type
   *   The field generation type.
   * @param array $custom_generation_types
   *   Custom configuration for generation type.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateMerge|bool
   *   Returns config template merge if custom config is found or false.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getCustomTemplateMerge($entity_type, $bundle, $field_name, $generation_type, array $custom_generation_types) {
    foreach ($custom_generation_types as $custom_generation_type_definition) {
      if ($custom_generation_type_definition['type'] === $generation_type) {
        $source_field_config = $custom_generation_type_definition['source'];

        /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
        $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($generation_type);

        /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
        return $config_template_type_plugin->getConfigChangesForField($entity_type, $bundle, $field_name, $source_field_config);
      }
    }

    return FALSE;
  }

  /**
   * Match field definition to field map.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $field_map_definition
   *   The field map configuration.
   *
   * @return bool
   *   Returns if field matches mapping.
   */
  protected function matchesFieldTypeMapping(FieldDefinitionInterface $field_definition, array $field_map_definition) {
    if ($field_definition->getType() !== $field_map_definition['type']) {
      return FALSE;
    }

    if (empty($field_map_definition['match_rules'])) {
      return TRUE;
    }

    foreach ($field_map_definition['match_rules'] as $rule_id => $definition) {
      if ($rule_id === 'storage_settings') {
        $storage_settings = $field_definition->getFieldStorageDefinition()
          ->getSettings();

        foreach ($definition as $key => $value) {
          if ($storage_settings[$key] !== $value) {
            return FALSE;
          }
        }
      }

      if ($rule_id === 'cardinality' && $field_definition->getFieldStorageDefinition()->getCardinality() !== $definition) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get dynamic mapping information for fields.
   *
   * @param array $source_config
   *   The source configuration.
   *
   * @return array
   *   Returns field definitions for dynamic part of source configuration.
   */
  protected function getDynamicFieldDefinitions(array $source_config) {
    $dynamic_field_mapping_definitions = [];
    foreach ($this->mappingConfig['generate_per_field'] as $generate_per_field) {
      $path = explode('.', $generate_per_field['name']);

      $dynamic_field_mapping_definitions[] = [
        'path' => $path,
        'type' => $generate_per_field['type'],
        'resource' => NestedArray::getValue($source_config, $path),
      ];
    }

    return $dynamic_field_mapping_definitions;
  }

  /**
   * Get dynamic mapping information for fields.
   *
   * @param array $source_config
   *   The source configuration.
   *
   * @return array
   *   Returns field definitions for dynamic part of source configuration.
   */
  protected function getDynamicViewDefinitions(array $source_config) {
    $dynamic_view_definitions = [];
    foreach ($this->mappingConfig['generate_per_view'] as $generate_per_view) {
      $path = explode('.', $generate_per_view['name']);

      $dynamic_view_definitions[] = [
        'path' => $path,
        'type' => $generate_per_view['type'],
        'resource' => NestedArray::getValue($source_config, $path),
      ];
    }

    return $dynamic_view_definitions;
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
    // 1. load template mapping information.
    $template_definition = $this->yamlSerializer->decode(file_get_contents($file_name));
    $this->mappingConfig = $template_definition['template'];

    // 2. load required source config.
    $source_config_name = ConfigName::createByFullName($this->mappingConfig['source']);
    $source_config = $this->configReverter->getFromExtension($source_config_name->getType(), $source_config_name->getName());

    // 3. prepare mapping information for fields and cleanup config.
    $dynamic_field_mapping_definitions = $this->getDynamicFieldDefinitions($source_config);
    $dynamic_view_definitions = $this->getDynamicViewDefinitions($source_config);

    $entity_type = $this->mappingConfig['entity_type'];
    $bundles = array_keys($this->bundleInfo->getBundleInfo($entity_type));
    foreach ($bundles as $bundle) {
      $this->configFactory->getEditable($this->mappingConfig['source'] . "_{$bundle}")
        ->setData($this->createConfigForBundle($entity_type, $bundle, $source_config, $dynamic_field_mapping_definitions, $dynamic_view_definitions))
        ->save(TRUE);
    }
  }

}
