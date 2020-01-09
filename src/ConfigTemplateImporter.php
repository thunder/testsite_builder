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
 * TODO: 1. Add support for custom configuration in mapping yaml file.
 * TODO:    - for example: filter/columns configuration for field type mapping.
 * TODO: 2. Add support for cardinality in field type matching rules.
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

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $bundle_fields */
    $bundle_fields = $this->fieldManager->getFieldDefinitions($this->mappingConfig['entity_type'], $bundle);

    foreach ($bundle_fields as $field_name => $field_definition) {
      // TODO: Add support for "filed_type_mapping" to include/exclude some
      // TODO: dynamically generated parts (fe. filter, columns, etc.)
      // TODO: It should be defined in field type mapping rule.
      foreach ($this->mappingConfig['filed_type_mapping'] as $filed_map) {
        // Skip computed fields.
        if ($field_definition->isComputed()) {
          continue;
        }

        if ($this->matchesFiledTypeMapping($field_definition, $filed_map)) {
          foreach ($dynamic_field_mapping_definitions as $dynamic_field_definition) {
            list($key, $value) = $this->configTemplateTypeManager->createInstance($dynamic_field_definition['type'])
              ->getConfigForField($entity_type, $field_name, $filed_map['source_field'], (empty($dynamic_field_definition['resource'])) ? [] : $dynamic_field_definition['resource']);

            // TODO: Add merge tactic in config template type result. Then we
            // TODO: use same logic for "getConfigForField" and
            // TODO: "getConfigForBundle" results.
            if (empty($key) && empty($value)) {
              continue;
            }

            $path = $dynamic_field_definition['path'];
            if (empty($key)) {
              $array_value = NestedArray::getValue($config, $path);
              $array_value[] = $value;
              NestedArray::setValue($config, $path, $array_value);

              continue;
            }

            $path[] = $key;
            NestedArray::setValue($config, $path, $value);
          }

          break;
        }
      }
    }

    foreach ($dynamic_view_definitions as $dynamic_view_definition) {
      list($key, $value) = $this->configTemplateTypeManager->createInstance($dynamic_view_definition['type'])
        ->getConfigForBundle($bundle, (empty($dynamic_view_definition['resource'])) ? [] : $dynamic_view_definition['resource']);

      if (empty($key) && empty($value)) {
        continue;
      }

      $path = $dynamic_view_definition['path'];
      NestedArray::setValue($config, $path, $value);
    }

    // Copy post generate.
    foreach ($this->mappingConfig['post_generate_clone'] as $copy_config_key) {
      $config = $this->copyFromSource($source_config, $copy_config_key['from'], $config, $copy_config_key['to']);
    }

    // Create ID for config.
    $config['id'] = "{$source_config['id']}_{$bundle}";

    return $config;
  }

  /**
   * Match field definition to field map.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $filed_map_definition
   *   The field map configuration.
   *
   * @return bool
   *   Returns if field matches mapping.
   */
  protected function matchesFiledTypeMapping(FieldDefinitionInterface $field_definition, array $filed_map_definition) {
    if ($field_definition->getType() !== $filed_map_definition['type']) {
      return FALSE;
    }

    if (isset($filed_map_definition['extra']['settings'])) {
      $settings = $field_definition->getSettings();

      $is_matched = TRUE;
      foreach ($filed_map_definition['extra']['settings'] as $key => $value) {
        if ($settings[$key] !== $value) {
          $is_matched = FALSE;

          break;
        }
      }

      return $is_matched;
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

    // TODO: Add intersection generation. That will generate config for fields
    // TODO: available in all bundles. Probably only base fields.
  }

}
