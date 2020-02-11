<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\update_helper\ConfigName;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for configuration of config template.
 *
 * @package Drupal\testsite_builder
 */
class ConfigTemplateDefinition {

  /**
   * The Yaml serializer.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $yamlSerializer;

  /**
   * The config reverter service from config update.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

  /**
   * The template configuration.
   *
   * @var array
   */
  protected $definition;

  /**
   * The directory where template is located.
   *
   * @var string
   */
  protected $templateDirectory;

  /**
   * The template source config.
   *
   * @var array
   */
  protected $sourceConfig;

  /**
   * The template fallback config.
   *
   * @var array
   */
  protected $fallbackConfig;

  /**
   * The config template definition constructor.
   *
   * @param \Drupal\Component\Serialization\SerializationInterface $yaml_serializer
   *   The Yaml serializer.
   * @param \Drupal\config_update\ConfigRevertInterface $config_reverter
   *   The config reverter service.
   */
  public function __construct(SerializationInterface $yaml_serializer, ConfigRevertInterface $config_reverter) {
    $this->yamlSerializer = $yaml_serializer;
    $this->configReverter = $config_reverter;
  }

  /**
   * Creates instance of config template definition.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The symfony container.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinition
   *   The instance of config template definition.
   */
  protected static function create(ContainerInterface $container): ConfigTemplateDefinition {
    return new static(
      $container->get('serialization.yaml'),
      $container->get('config_update.config_update')
    );
  }

  /**
   * Create instance from file.
   *
   * @param string $file_name
   *   The template mapping configuration file.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinition
   *   Returns instance of config template definition.
   *
   * @throws \Exception
   */
  public static function createFromFile($file_name) {
    $instance = static::create(\Drupal::getContainer());
    $template_definition = $instance->yamlSerializer->decode(file_get_contents($file_name));

    return static::createFromDefinition(dirname($file_name), $template_definition['template']);
  }

  /**
   * Get directory where template is located.
   *
   * @return string
   *   Returns directory where template is located.
   */
  public function getTemplateDirectory() {
    return $this->templateDirectory;
  }

  /**
   * Create new config template definition from defintion array.
   *
   * @param string $template_directory
   *   The directory where template is located.
   * @param array $definition
   *   The config template defintion.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinition
   *   Returns instance of config template definition.
   *
   * @throws \Exception
   */
  public static function createFromDefinition($template_directory, array $definition) {
    $instance = static::create(\Drupal::getContainer());
    $instance->definition = $definition;
    $instance->templateDirectory = $template_directory;

    // Load fallback.
    if (empty($instance->definition['fallback'])) {
      throw new \Exception("Configuration template definition requires fallback config.");
    }
    $instance->fallbackConfig = $instance->yamlSerializer->decode(file_get_contents($instance->templateDirectory . '/fallback/' . $instance->definition['fallback'] . '.yml'));

    return $instance;
  }

  /**
   * Get config template definition property.
   *
   * @param string $key
   *   The key for template.
   *
   * @return mixed
   *   Returns value of base template definition property.
   */
  public function getKey($key) {
    if (!isset($this->definition[$key])) {
      return NULL;
    }

    return $this->definition[$key];
  }

  /**
   * Get template source configuration.
   *
   * @return array
   *   Returns source config of the template.
   */
  public function getSourceConfig() {
    if (empty($this->sourceConfig)) {
      $source_config_name = ConfigName::createByFullName($this->definition['source']);
      $this->sourceConfig = $this->configReverter->getFromExtension($source_config_name->getType(), $source_config_name->getName());
    }

    return $this->sourceConfig;
  }

  /**
   * Create copy of source configuration with cleaned dynamic parts.
   *
   * @return array
   *   Returns cleaned configuration.
   */
  public function getCleanConfig() {
    $config = $this->getSourceConfig();

    // Clean-Up field related properties, that will be generated dynamically.
    foreach ($this->definition['generate_per_field'] as $generate_per_field) {
      NestedArray::setValue($config, explode('.', $generate_per_field['name']), []);
    }

    return $config;
  }

  /**
   * Get dynamic mapping information from definition.
   *
   * @param string $key
   *   The property for dynamic definition part.
   *
   * @return array
   *   Returns definitions for dynamic part of source configuration.
   */
  protected function getDynamicDefinitions($key) {
    $dynamic_mapping_definitions = [];
    foreach ($this->definition[$key] as $generate_per_field) {
      $path = array_filter(explode('.', $generate_per_field['name']));

      $dynamic_mapping_definitions[] = [
        'path' => $path,
        'type' => $generate_per_field['type'],
      ];
    }

    return $dynamic_mapping_definitions;
  }

  /**
   * Get dynamic config definition for field with fallback.
   *
   * @param array $path
   *   The configuration path.
   * @param array $source_field_names
   *   The list of possible config names for source field name.
   * @param array $fallback_field_names
   *   The fallback field name defined in mapping.
   *
   * @return array|mixed
   *   Returns array with configuration for provided path and field.
   */
  public function getDynamicSourceDefinitionForField(array $path, array $source_field_names = [], array $fallback_field_names = []) {
    // Use defined mapping to source field.
    if (!empty($source_field_names)) {
      $source_config = $this->getSourceConfig();
      foreach ($source_field_names as $source_field_name) {
        $source_field_value = NestedArray::getValue($source_config, array_merge($path, [$source_field_name]));
        if (!empty($source_field_value)) {
          return $source_field_value;
        }
      }
    }

    // Use fallback field.
    foreach ($fallback_field_names as $fallback_field_name) {
      $fallback_config = NestedArray::getValue($this->fallbackConfig, array_merge($path, [$fallback_field_name]));
      if (!empty($fallback_config)) {
        return $fallback_config;
      }
    }

    return [];
  }

  /**
   * Get dynamic config definition for field with fallback.
   *
   * @param array $path
   *   The configuration path.
   *
   * @return array|mixed
   *   Returns array with configuration for provided path and field.
   */
  public function getDynamicSourceDefinition(array $path) {
    $source_config = $this->getSourceConfig();
    $source_definition = NestedArray::getValue($source_config, $path);

    if (!empty($source_definition)) {
      return $source_definition;
    }

    return [];
  }

  /**
   * Get dynamic mapping information for fields.
   *
   * @return array
   *   Returns field definitions for dynamic part of source configuration.
   */
  public function getDynamicFieldDefinitions() {
    return $this->getDynamicDefinitions('generate_per_field');
  }

  /**
   * Get dynamic mapping information for bundles.
   *
   * @return array
   *   Returns field definitions for dynamic part of source configuration.
   */
  public function getDynamicBundleDefinitions() {
    return $this->getDynamicDefinitions('generate_per_bundle');
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
  public function findFieldMapping(FieldDefinitionInterface $field_definition) {
    if (empty($this->definition['field_type_mapping'])) {
      return FALSE;
    }

    foreach ($this->definition['field_type_mapping'] as $field_map) {
      if ($this->matchesFieldTypeMapping($field_definition, $field_map)) {
        return $field_map;
      }
    }

    // Use fallback match.
    return [
      'type' => $field_definition->getType(),
    ];
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

}
