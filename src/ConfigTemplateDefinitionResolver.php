<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\update_helper\ConfigName;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config template definition resolver.
 *
 * @package Drupal\testsite_builder
 */
class ConfigTemplateDefinitionResolver {

  /**
   * The config template definition.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateDefinition
   */
  protected $templateDefinition;

  /**
   * The config template type manager service.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateTypePluginManager
   */
  protected $configTemplateTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The queue of field configurations what should be created.
   *
   * @var array
   */
  protected $fieldTemplatesQueue = [];

  /**
   * The configuration template collection.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateDefinitionCollection
   */
  protected $collection;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The config template definition constructor.
   *
   * @param \Drupal\testsite_builder\ConfigTemplateTypePluginManager $config_template_type_manager
   *   The config template type manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager service.
   */
  protected function __construct(ConfigTemplateTypePluginManager $config_template_type_manager, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager) {
    $this->configTemplateTypeManager = $config_template_type_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
  }

  /**
   * Creates instance of config template definition.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The symfony container.
   * @param \Drupal\testsite_builder\ConfigTemplateDefinitionCollection $collection
   *   The config template collection.
   *
   * @return \Drupal\testsite_builder\ConfigTemplateDefinitionResolver
   *   The instance of config template definition resolver.
   */
  public static function create(ContainerInterface $container, ConfigTemplateDefinitionCollection $collection): ConfigTemplateDefinitionResolver {
    $instance = new static(
      $container->get('testsite_builder.config_template_type_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );

    $instance->collection = $collection;
    $instance->entityType = $collection->getEntityType();

    return $instance;
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
   * @param array $dynamic_bundle_definitions
   *   The dynamic bundle definitions.
   * @param array $dynamic_field_mapping_definitions
   *   The dynamic field mapping definitions.
   *
   * @return array
   *   Returns new generated configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createConfig($entity_type, $bundle, array $dynamic_bundle_definitions, array $dynamic_field_mapping_definitions) {
    $config = $this->templateDefinition->getCleanConfig();

    // Copy pre generate.
    $pre_generate_clone = $this->templateDefinition->getKey('pre_generate_clone');
    if (!empty($pre_generate_clone)) {
      foreach ($pre_generate_clone as $clone_config_key) {
        $config = $this->copyFromSource($config, $clone_config_key['from'], $clone_config_key['to']);
      }
    }

    // Generate dynamic configuration for fields.
    if (!empty($dynamic_field_mapping_definitions)) {
      $config = $this->applyConfigurationForFields($entity_type, $bundle, $config, $dynamic_field_mapping_definitions);
    }

    // Generate dynamic configuration for bundle.
    if (!empty($dynamic_bundle_definitions)) {
      $config = $this->applyConfigurationForBundle($entity_type, $bundle, $config, $dynamic_bundle_definitions);
    }

    // Copy post generate.
    $post_generate_clone = $this->templateDefinition->getKey('post_generate_clone');
    if (!empty($post_generate_clone)) {
      foreach ($post_generate_clone as $clone_config_key) {
        $config = $this->copyFromSource($config, $clone_config_key['from'], $clone_config_key['to']);
      }
    }

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
   * @throws \Exception
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
          empty($field_map['source_field']) ? [] : $config_template_type_plugin->getPossibleFieldSourceConfigKeys($field_definition, $field_map['source_field']),
          $config_template_type_plugin->getPossibleFieldSourceConfigKeys($field_definition, empty($field_map['fallback_field']) ? '' : $field_map['fallback_field'])
        );

        /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
        $config_template_merge = $config_template_type_plugin->getConfigChangesForField($this->collection->getId(), $entity_type, $bundle, $field_name, $source_field_config);
        $config = $config_template_merge->applyMerge($config, $dynamic_field_definition['path']);
      }

      if (!empty($field_map['templates'])) {
        $this->createAllFieldConfigurations($entity_type, $bundle, $field_definition, $field_map['templates']);
      }
    }

    return $config;
  }

  /**
   * Create field related configurations.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $field_templates
   *   The list of configuration templates for field.
   *
   * @throws \Exception
   */
  protected function createAllFieldConfigurations(string $entity_type, string $bundle, FieldDefinitionInterface $field_definition, array $field_templates) {
    $field_collection = ConfigTemplateDefinitionCollection::createFromTemplates($this->collection->getId(), $this->entityType, $field_templates, $this->collection->getDirectory());
    $config_resolver = ConfigTemplateDefinitionResolver::create(\Drupal::getContainer(), $field_collection);

    foreach ($field_collection->getDefinitionNames() as $definition_name) {
      $field_configuration_template = $field_collection->getDefinition($definition_name);

      $this->fieldTemplatesQueue[] = [
        'config_resolver' => $config_resolver,
        'field_configuration_template' => $field_configuration_template,
        'bundle' => $bundle,
        'field_name' => $field_definition->getName(),
      ];
    }
  }

  /**
   * Create configuration for field.
   *
   * @param \Drupal\testsite_builder\ConfigTemplateDefinition $configuration_template
   *   The configuration template array.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createConfigForField(ConfigTemplateDefinition $configuration_template, string $entity_type, string $bundle, string $field_name) {
    $this->templateDefinition = $configuration_template;

    $dynamic_bundle_definitions = $this->templateDefinition->getDynamicBundleDefinitions();

    $field_config = $this->createConfig($entity_type, $bundle, $dynamic_bundle_definitions, []);
    $config_parts = explode('.', $this->templateDefinition->getKey('source'));

    // Create ID for config.
    array_pop($config_parts);
    $config_id = "{$this->collection->getId()}_{$entity_type}_{$bundle}_{$field_name}";
    $field_config['id'] = $config_id;
    array_push($config_parts, $config_id);

    if (!empty($this->templateDefinition->getKey('full_template_plugin'))) {
      /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
      $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($this->templateDefinition->getKey('full_template_plugin'));

      /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
      $config_template_merge = $config_template_type_plugin->getConfigChangesForField($this->collection->getId(), $entity_type, $bundle, $field_name, $field_config);
      $field_config = $config_template_merge->applyMerge($field_config, []);
    }

    $config_name = ConfigName::createByFullName(implode('.', $config_parts));

    $this->saveConfig($field_config, $config_name);
  }

  /**
   * Create configuration for bundle.
   *
   * @param \Drupal\testsite_builder\ConfigTemplateDefinition $configuration_template
   *   The configuration template definition.
   * @param string $bundle
   *   The bundle.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createConfigForBundle(ConfigTemplateDefinition $configuration_template, string $bundle) {
    $this->templateDefinition = $configuration_template;

    $dynamic_field_mapping_definitions = $this->templateDefinition->getDynamicFieldDefinitions();
    $dynamic_bundle_definitions = $this->templateDefinition->getDynamicBundleDefinitions();

    $bundle_config = $this->createConfig($this->entityType, $bundle, $dynamic_bundle_definitions, $dynamic_field_mapping_definitions);

    // Create ID for config.
    $bundle_config['id'] = "{$this->collection->getId()}_{$this->entityType}_{$bundle}";
    $config_name = ConfigName::createByTypeName(
      ConfigName::createByFullName($this->templateDefinition->getKey('source'))
        ->getType(),
      $bundle_config['id']
    );

    $this->saveConfig($bundle_config, $config_name);

    // After base bundle configuration is saved, process queue for fields.
    $this->processFieldQueue();
  }

  /**
   * Handle configuration creation for delayed field related configurations.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processFieldQueue() {
    // Process field queue.
    foreach ($this->fieldTemplatesQueue as $job_definition) {
      /** @var \Drupal\testsite_builder\ConfigTemplateDefinitionResolver $resolver */
      $resolver = $job_definition['config_resolver'];
      $resolver->createConfigForField($job_definition['field_configuration_template'], $this->entityType, $job_definition['bundle'], $job_definition['field_name']);
    }
  }

  /**
   * Save create configuration.
   *
   * @param array $config
   *   The configuration.
   * @param \Drupal\update_helper\ConfigName $config_name
   *   The configuration name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveConfig(array $config, ConfigName $config_name) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $config_entity_storage */
    $config_entity_storage = $this->entityTypeManager->getStorage($config_name->getType());

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $config_entity */
    $config_entity = $config_entity_storage->load($config_name->getName());
    $config_entity = ($config_entity === NULL) ? $config_entity_storage->createFromStorageRecord($config) : $config_entity_storage->updateFromStorageRecord($config_entity, $config);
    $config_entity->save();
  }

  /**
   * Applies dynamic configuration for bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle name.
   * @param array $config
   *   The configuration.
   * @param array $dynamic_bundle_definitions
   *   The dynamic definitions from template file for bundle.
   *
   * @return array
   *   Returns configuration with new changes.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function applyConfigurationForBundle($entity_type, $bundle, array $config, array $dynamic_bundle_definitions) {
    foreach ($dynamic_bundle_definitions as $dynamic_bundle_definition) {
      $source_bundle_config = $this->templateDefinition->getDynamicSourceDefinition($dynamic_bundle_definition['path']);

      /** @var \Drupal\testsite_builder\ConfigTemplateTypeInterface $config_template_type_plugin */
      $config_template_type_plugin = $this->configTemplateTypeManager->createInstance($dynamic_bundle_definition['type']);

      /** @var \Drupal\testsite_builder\ConfigTemplateMerge $config_template_merge */
      $config_template_merge = $config_template_type_plugin->getConfigChangesForBundle($this->collection->getId(), $entity_type, $bundle, $source_bundle_config);
      $config = $config_template_merge->applyMerge($config, $dynamic_bundle_definition['path']);
    }

    return $config;
  }

}
