<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\ConfigImporter;

use Drupal\config_update\ConfigRevertInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\testsite_builder\ConfigImporterInterface;
use Drupal\update_helper\ConfigName;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config importer plugin for entity_browser configuration type.
 *
 * @TestsiteBuilderConfigImporter(
 *   id = "entity_browser",
 *   label = @Translation("Generic"),
 *   description = @Translation("Generic config importer plugin.")
 * )
 */
class EntityBrowser extends PluginBase implements ConfigImporterInterface, ContainerFactoryPluginInterface {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config reverter service.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configReverter;

  /**
   * List of entity browser widgets supported by configuration modifications.
   *
   * @var array
   */
  protected $supportedEntityBrowserWidgets = [
    'dropzonejs_media_entity',
    'dropzonejs_media_entity_inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ConfigRevertInterface $config_reverter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->configReverter = $config_reverter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('config_update.config_update'));
  }

  /**
   * {@inheritdoc}
   */
  public function importConfig(string $dependent, string $missing) {
    $dependent_config_name = ConfigName::createByFullName($dependent);

    // Currently we support only adjusting of configuration for field widget.
    if ($dependent_config_name->getType() != 'entity_form_display') {
      $this->importForFieldWidget($dependent_config_name, ConfigName::createByFullName($missing));
    }
  }

  /**
   * Import entity browser configuration for field widget.
   *
   * @param \Drupal\update_helper\ConfigName $dependent_config_name
   *   The dependent configuration name.
   * @param \Drupal\update_helper\ConfigName $missing_config_name
   *   The missing configuration name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function importForFieldWidget(ConfigName $dependent_config_name, ConfigName $missing_config_name) {
    $dependent_config = $this->configReverter->getFromActive($dependent_config_name->getType(), $dependent_config_name->getName());

    // We should handle all displayed fields that depend on an entity browser.
    foreach ($dependent_config['content'] as $field_name => $field_config) {
      // Find only field widgets with the missing config of entity browser.
      if (empty($field_config['settings']['entity_browser']) || $field_config['settings']['entity_browser'] != $missing_config_name->getName()) {
        continue;
      }

      // We need target bundles in order to change settings of entity browser.
      $field_field_config = $this->configReverter->getFromActive('field_config', sprintf('%s.%s.%s', $dependent_config['targetEntityType'], $dependent_config['bundle'], $field_name));
      if (empty($field_field_config['settings']['handler_settings']['target_bundles'])) {
        continue;
      }

      // Entity browser widget configuration expects only one media type.
      $target_bundles = $field_field_config['settings']['handler_settings']['target_bundles'];
      if (count($target_bundles) !== 1) {
        continue;
      }

      $new_config_name = $this->prepareEntityBrowserConfig(array_pop($target_bundles), $missing_config_name);
      $this->setEntityBrowserForFieldWidget($field_name, $dependent_config_name, $new_config_name->getName());
    }
  }

  /**
   * Set entity browser for field widget.
   *
   * @param string $field_name
   *   The field name.
   * @param \Drupal\update_helper\ConfigName $dependent_config_name
   *   The dependent form display configuration name.
   * @param string $entity_browser_name
   *   The entity browser for field widget.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setEntityBrowserForFieldWidget(string $field_name, ConfigName $dependent_config_name, string $entity_browser_name) {
    $form_display_entity_storage = $this->entityTypeManager->getStorage($dependent_config_name->getType());
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $entity */
    $form_display_entity = $form_display_entity_storage->load($dependent_config_name->getName());
    $display_content = $form_display_entity->get('content');
    $display_content[$field_name]['settings']['entity_browser'] = $entity_browser_name;
    $form_display_entity->set('content', $display_content);
    $form_display_entity->calculateDependencies();
    $form_display_entity->save();
  }

  /**
   * Create new entity browser configuration and import it when needed.
   *
   * @param string $target_bundle
   *   The media entity bundle.
   * @param \Drupal\update_helper\ConfigName $missing_config_name
   *   The missing configuration name, used as base for creation of new one.
   *
   * @return \Drupal\update_helper\ConfigName
   *   Returns newly created config name or already existing one.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function prepareEntityBrowserConfig(string $target_bundle, ConfigName $missing_config_name): ConfigName {
    $new_config_name = ConfigName::createByTypeName(
      $missing_config_name->getType(),
      $missing_config_name->getName() . '_' . $target_bundle
    );

    // Already existing entity browser for target bundle can be reused.
    if ($this->configReverter->getFromActive($new_config_name->getType(), $new_config_name->getName()) !== FALSE) {
      return $new_config_name;
    }

    // Create new entity browser configuration for target bundle.
    $required_config = $this->configReverter->getFromExtension($missing_config_name->getType(), $missing_config_name->getName());
    $required_config['name'] = $new_config_name->getName();

    // Apply adjustments for entity browser widgets if they are necessary.
    foreach ($required_config['widgets'] as &$widget_config) {
      if (!in_array($widget_config['id'], $this->supportedEntityBrowserWidgets)) {
        continue;
      }

      $widget_config['settings']['media_type'] = $target_bundle;
    }

    // Import new entity browser configuration.
    $entity_browser_storage = $this->entityTypeManager->getStorage($new_config_name->getType());
    $entity_browser = $entity_browser_storage->createFromStorageRecord($required_config);
    $entity_browser->save();

    return $new_config_name;
  }

}
