<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\SerializationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Collection of configuration template definitions.
 *
 * @package Drupal\testsite_builder
 */
class ConfigTemplateDefinitionCollection {

  /**
   * The Yaml serializer.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $yamlSerializer;

  /**
   * The collection ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The entity type for this collection.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The template definitions.
   *
   * @var array
   */
  protected $templates;

  /**
   * The directory where collection file is located.
   *
   * @var string
   */
  protected $directory;

  /**
   * The config template definition collection constructor.
   *
   * @param \Drupal\Component\Serialization\SerializationInterface $yaml_serializer
   *   The Yaml serializer.
   */
  protected function __construct(SerializationInterface $yaml_serializer) {
    $this->yamlSerializer = $yaml_serializer;
  }

  /**
   * Creates instance of config template definition collection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The symfony container.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinitionCollection
   *   The instance of config template definition collection.
   */
  protected static function create(ContainerInterface $container): ConfigTemplateDefinitionCollection {
    return new static(
      $container->get('serialization.yaml')
    );
  }

  /**
   * Validate that all required source configurations are available.
   *
   * @return bool
   *   Returns TRUE if collection is valid, otherwise it throws.
   *
   * @throws \Exception
   */
  protected function validate(): bool {
    foreach ($this->templates as $template_name => $template) {
      /** @var \Drupal\testsite_builder\ConfigTemplateDefinition $config_template_definition */
      $config_template_definition = $this->getDefinition($template_name);
      if ($config_template_definition->getSourceConfig() === FALSE) {
        throw new \Exception("Unable to find configuration {$template['source']} used by template collection {$this->id}.");
      }

      $field_mappings = $config_template_definition->getKey('field_type_mapping');
      foreach ($field_mappings as $field_mapping) {
        if (!empty($field_mapping['templates'])) {
          ConfigTemplateDefinitionCollection::createFromTemplates($this->getId(), $this->entityType, $field_mapping['templates'], $this->getDirectory());
        }
      }
    }

    return TRUE;
  }

  /**
   * Create new config template definition collection from templates.
   *
   * @param string $id
   *   The collection ID.
   * @param string $entity_type
   *   The entity type for this collection.
   * @param array $templates
   *   The list of raw config template definitions.
   * @param string $directory
   *   The directory where collection is located.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinitionCollection
   *   Returns instance of config template definition collection.
   *
   * @throws \Exception
   *   Throws if collection is not valid.
   */
  public static function createFromTemplates(string $id, string $entity_type, array $templates, $directory): ConfigTemplateDefinitionCollection {
    $instance = static::create(\Drupal::getContainer());

    $instance->id = $id;
    $instance->entityType = $entity_type;
    $instance->templates = $templates;
    $instance->directory = $directory;

    // Validate collection.
    $instance->validate();

    return $instance;
  }

  /**
   * Create instance from file.
   *
   * @param string $file_name
   *   The config template collection configuration file.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinitionCollection
   *   Returns instance of config template definition collection.
   *
   * @throws \Exception
   *   Throws if collection is not valid.
   */
  public static function createFromFile($file_name): ConfigTemplateDefinitionCollection {
    $instance = static::create(\Drupal::getContainer());
    $collection = $instance->yamlSerializer->decode(file_get_contents($file_name));

    return static::createFromTemplates($collection['id'], $collection['entity_type'], $collection['templates'], dirname($file_name));
  }

  /**
   * Get collection ID.
   *
   * @return string
   *   Returns collection ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get entity type.
   *
   * @return string
   *   Returns entity type for this collection.
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * Get directory for this collection.
   *
   * @return string
   *   Returns collection directory.
   */
  public function getDirectory(): string {
    return $this->directory;
  }

  /**
   * Gets available definition names.
   *
   * @return array
   *   Returns definition names for this collection.
   */
  public function getDefinitionNames(): array {
    return array_keys($this->templates);
  }

  /**
   * Returns config template definition from collection.
   *
   * @param string $name
   *   The config definition name within collection.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinition
   *   The instance of config template definition.
   *
   * @throws \Exception
   */
  public function getDefinition($name): ConfigTemplateDefinition {
    if (empty($this->templates[$name])) {
      throw new \Exception("Configuration template '{$name}' does not exist in collection {$this->id}.");
    }

    $definition = $this->templates[$name];
    $fallback = [];
    if (!empty($definition['fallback'])) {
      $fallback = $this->yamlSerializer->decode(file_get_contents($this->directory . '/fallback/' . $definition['fallback'] . '.yml'));
    }

    return ConfigTemplateDefinition::createFromDefinition($definition, $fallback);
  }

}
