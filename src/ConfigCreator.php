<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityDependency;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\testsite_builder\Events\ConfigCreatorEntityBundleCreateEvent;
use Drupal\testsite_builder\Events\ConfigCreatorEvents;
use Drupal\testsite_builder\Events\ConfigCreatorFieldCreateEvent;
use Drupal\update_helper\ConfigName;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The ConfigCreator class.
 *
 * @package Drupal\testsite_builder
 */
class ConfigCreator {

  /**
   * The report data.
   *
   * @var array
   */
  protected $reportData;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The field type manager service.
   *
   * @var \Drupal\testsite_builder\FieldTypePluginManager
   */
  protected $fieldTypePluginManager;

  /**
   * The content creator config storage service.
   *
   * @var \Drupal\testsite_builder\ContentCreatorStorage
   */
  protected $contentCreatorStorage;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\testsite_builder\EntityTypePluginManager
   */
  protected $entityTypePluginManager;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config importer plugin manager.
   *
   * @var \Drupal\testsite_builder\ConfigImporterPluginManager
   */
  protected $configImporterPluginManager;

  /**
   * The config template importer service.
   *
   * @var \Drupal\testsite_builder\ConfigTemplateImporter
   */
  protected $configTemplateImporter;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new ConfigCreator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\testsite_builder\FieldTypePluginManager $fieldTypePluginManager
   *   The field type manager service.
   * @param \Drupal\testsite_builder\EntityTypePluginManager $entityTypePluginManager
   *   The entity type manager service.
   * @param \Drupal\testsite_builder\ConfigImporterPluginManager $configImporterPluginManager
   *   The config importer plugin manager.
   * @param \Drupal\testsite_builder\ConfigTemplateImporter $configTemplateImporter
   *   The config template importer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\testsite_builder\ContentCreatorStorage $content_creator_storage
   *   The content creator storage service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, FieldTypePluginManager $fieldTypePluginManager, EntityTypePluginManager $entityTypePluginManager, ConfigImporterPluginManager $configImporterPluginManager, ConfigTemplateImporter $configTemplateImporter, ConfigFactoryInterface $configFactory, EventDispatcherInterface $event_dispatcher, ContentCreatorStorage $content_creator_storage, Connection $connection) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
    $this->entityTypePluginManager = $entityTypePluginManager;
    $this->configImporterPluginManager = $configImporterPluginManager;
    $this->configTemplateImporter = $configTemplateImporter;
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $event_dispatcher;
    $this->contentCreatorStorage = $content_creator_storage;
    $this->database = $connection;
  }

  /**
   * Sets report data.
   *
   * @param string $file
   *   A report file.
   *
   * @return \Drupal\testsite_builder\ConfigCreator
   *   This class.
   */
  public function setReportData(string $file) : ConfigCreator {
    $this->reportData = Json::decode(file_get_contents($file));
    return $this;
  }

  /**
   * Deletes all bundle configs.
   *
   * @return \Drupal\testsite_builder\ConfigCreator
   *   This class.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanup() : ConfigCreator {
    try {
      foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
        $index->delete();
      }
    }
    catch (\Exception $e) {
      // Search API is not installed.
    }

    foreach ($this->getEntityTypes() as $entity_type) {
      $definition = $this->entityTypeManager->getDefinition($entity_type);
      $bundleEntityType = $definition->getBundleEntityType();
      if ($bundleEntityType) {
        // Delete all entities.
        $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
        $storage = $this->entityTypeManager->getStorage($entity_type);
        foreach ($storage->getTableMapping($field_definitions)->getTableNames() as $table) {
          $this->database->truncate($table)->execute();
        }

        // Delete fields.
        $entities = $this->entityTypeManager->getStorage('field_config')->loadByProperties(['entity_type' => $entity_type]);
        $this->entityTypeManager->getStorage('field_config')->delete($entities);

        $entities = $this->entityTypeManager->getStorage('field_storage_config')->loadByProperties(['entity_type' => $entity_type]);
        $this->entityTypeManager->getStorage('field_storage_config')->delete($entities);
      }
    }

    foreach ($this->getEntityTypes() as $entity_type) {
      $definition = $this->entityTypeManager->getDefinition($entity_type);
      $bundleEntityType = $definition->getBundleEntityType();
      if ($bundleEntityType) {
        // Delete all bundles.
        $bundles = $this->entityTypeManager->getStorage($bundleEntityType)->loadMultiple();
        $this->entityTypeManager->getStorage($bundleEntityType)->delete($bundles);
      }
    }

    return $this;
  }

  /**
   * Creates new bundles.
   *
   * @return \Drupal\testsite_builder\ConfigCreator
   *   This class.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function create(): ConfigCreator {
    foreach ($this->getEntityTypes() as $entity_type) {
      if (!isset($this->reportData[$entity_type]['bundle'])) {
        continue;
      }

      $configuration = ['entity_type' => $entity_type];
      /** @var \Drupal\testsite_builder\EntityTypeInterface $testbuilder_entity_type */
      $testbuilder_entity_type = $this->entityTypePluginManager->createInstance($entity_type, ['entity_type' => $entity_type]);
      foreach ($this->reportData[$entity_type]['bundle'] as $bundle_id => $bundle_config) {
        if (!$testbuilder_entity_type->isApplicable($bundle_config)) {
          continue;
        }

        // Create bundle.
        $bundle = $testbuilder_entity_type->createBundle($bundle_id, $bundle_config);
        $this->eventDispatcher->dispatch(ConfigCreatorEvents::ENTITY_BUNDLE_CREATE, new ConfigCreatorEntityBundleCreateEvent($bundle, $bundle_config));
        $configuration['bundle_type'] = $bundle_id;

        foreach ($bundle_config['fields'] as $field_name => $field_instance) {
          $field_configuration = $configuration + $field_instance + ['field_name' => $field_name];

          /** @var \Drupal\testsite_builder\FieldTypeInterface $testbuilder_field_type */
          $testbuilder_field_type = $this->fieldTypePluginManager->createInstance($field_instance['type'], $field_configuration);
          if (!$testbuilder_field_type->isApplicable()) {
            continue;
          }

          $field = $testbuilder_field_type->createField();
          $this->eventDispatcher->dispatch(ConfigCreatorEvents::FIELD_CREATE, new ConfigCreatorFieldCreateEvent($field, $field_configuration));
        }

        $testbuilder_entity_type->postCreate($bundle, $bundle_config);
      }
    }

    return $this;
  }

  /**
   * Discover and fix missing configuration.
   *
   * @return array
   *   List of imported configuration dependencies per dependent configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function fixMissingConfiguration(): array {
    /** @var array $missing_configurations */
    $missing_configurations = [];

    $all_configs = $this->configFactory->listAll();
    foreach ($all_configs as $config) {
      $config_dependency = new ConfigEntityDependency(
        $config,
        $this->configFactory->get($config)->getRawData()
      );

      $missing_configs = array_diff($config_dependency->getDependencies('config'), $all_configs);
      foreach ($missing_configs as $missing_config) {
        $missing_config_name = ConfigName::createByFullName($missing_config);
        /** @var \Drupal\testsite_builder\ConfigImporterInterface $config_importer */
        $config_importer = $this->configImporterPluginManager->createInstance($missing_config_name->getType());
        $config_importer->importConfig($config, $missing_config_name->getFullName());
        $missing_configurations[$missing_config][] = $config;
      }
    }

    return $missing_configurations;
  }

  /**
   * Import template configurations.
   */
  public function importTemplateConfigurations() {
    $template_path = realpath(drupal_get_path('module', 'testsite_builder') . '/template');

    $importedTemplates = [];
    $failedTemplates = [];
    foreach (glob("{$template_path}/*.yml") as $template_file) {
      $template_file_name = basename($template_file);
      try {
        $this->configTemplateImporter->loadTemplate($template_file);
        $importedTemplates[] = $template_file_name;
      }
      catch (\Exception $e) {
        $failedTemplates[] = "Loading of template {$template_file_name} failed with error: {$e->getMessage()}";
      }
    }

    return [
      'imported' => $importedTemplates,
      'errors' => $failedTemplates,
    ];
  }

  /**
   * Get a list of all supported entity types.
   *
   * @return array
   *   List of supported entity types.
   */
  protected function getEntityTypes() : array {
    $existingData = $this->entityTypeManager->getDefinitions();

    $entity_types = array_keys(array_intersect_key($this->reportData, $existingData));
    return array_diff($entity_types, [
      // TODO: Remove when crop type is exported.
      'crop',
      'update_helper_checklist_update',
      'access_token',
      'menu_link_content',
      'redirect',
      'shortcut',
    ]);
  }

}
