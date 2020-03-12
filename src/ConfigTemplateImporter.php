<?php

namespace Drupal\testsite_builder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Constructs a new ConfigTemplateImporter object.
   *
   * @param \Drupal\testsite_builder\ConfigTemplateTypePluginManager $config_template_type_manager
   *   The config template type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager service.
   */
  public function __construct(ConfigTemplateTypePluginManager $config_template_type_manager, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info, EntityFieldManagerInterface $field_manager) {
    $this->configTemplateTypeManager = $config_template_type_manager;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->fieldManager = $field_manager;
  }

  /**
   * Load template configuration from yaml file.
   *
   * @param string $file_name
   *   The file name.
   *
   * @throws \Exception
   *   Throws if collection is not valid.
   */
  public function loadTemplate($file_name) {
    // 1. load template mapping definition.
    $collection = ConfigTemplateDefinitionCollection::createFromFile($file_name);

    // 2. get template resolver.
    $config_resolver = ConfigTemplateDefinitionResolver::create(\Drupal::getContainer(), $collection);

    // 3. create configuration for bundles.
    $bundles = array_keys($this->bundleInfo->getBundleInfo($collection->getEntityType()));
    foreach ($collection->getDefinitionNames() as $definition_name) {
      foreach ($bundles as $bundle) {
        $config_resolver->createConfigForBundle($collection->getDefinition($definition_name), $bundle);
      }
    }
  }

}
