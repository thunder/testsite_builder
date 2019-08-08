<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityDependency;
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
   * List of imported missing configurations.
   *
   * @var array
   */
  protected $importedConfigurations = [];

  /**
   * Constructs a new ConfigCreator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   * @param \Drupal\testsite_builder\FieldTypePluginManager $fieldTypePluginManager
   *   The field type manager service.
   * @param \Drupal\testsite_builder\EntityTypePluginManager $entityTypePluginManager
   *   The entity type manager service.
   * @param \Drupal\testsite_builder\ConfigImporterPluginManager $configImporterPluginManager
   *   The config importer plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\testsite_builder\ContentCreatorStorage $content_creator_storage
   *   The content creator storage service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FieldTypePluginManager $fieldTypePluginManager, EntityTypePluginManager $entityTypePluginManager, ConfigImporterPluginManager $configImporterPluginManager, ConfigFactoryInterface $configFactory, EventDispatcherInterface $event_dispatcher, ContentCreatorStorage $content_creator_storage) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
    $this->entityTypePluginManager = $entityTypePluginManager;
    $this->configImporterPluginManager = $configImporterPluginManager;
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $event_dispatcher;
    $this->contentCreatorStorage = $content_creator_storage;
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
    foreach ($this->getEntityTypes() as $entity_type) {
      $definition = $this->entityTypeManager->getDefinition($entity_type);
      $bundleEntityType = $definition->getBundleEntityType();
      if ($bundleEntityType) {
        // Delete all entities.
        $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
        $this->entityTypeManager->getStorage($entity_type)->delete($entities);

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

        // Recalculate dependencies for display form after bundle is created.
        $form_display_entity = $this->entityTypeManager->getStorage('entity_form_display')->load(sprintf('%s.%s.%s', $entity_type, $bundle_id, 'default'));
        $form_display_entity->calculateDependencies();
        $form_display_entity->save();

        $testbuilder_entity_type->postCreate($bundle, $bundle_config);
      }
    }

    $this->fixMissingConfiguration();

    return $this;
  }

  /**
   * Discover and fix missing configuration.
   */
  protected function fixMissingConfiguration() {
    $config_names = array_flip($this->configFactory->listAll());
    foreach (array_keys($config_names) as $config_name) {
      $config_dependency = new ConfigEntityDependency(
        $config_name,
        $this->configFactory->get($config_name)->getRawData()
      );

      foreach ($config_dependency->getDependencies('config') as $required_config) {
        if (!isset($config_names[$required_config])) {
          $missing_config_name = ConfigName::createByFullName($required_config);

          /** @var \Drupal\testsite_builder\ConfigImporterInterface $config_importer */
          $config_importer = $this->configImporterPluginManager->createInstance($missing_config_name->getType());
          $config_importer->importConfig($config_name, $missing_config_name->getFullName());

          $this->importedConfigurations[$config_name][] = $required_config;
        }
      }
    }
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

  /**
   * Get list of imported missing configurations.
   *
   * @return array
   *   The list of imported configurations.
   */
  public function getImportedConfigurations() {
    return $this->importedConfigurations;
  }

}
