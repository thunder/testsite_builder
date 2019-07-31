<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\EntityType;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\testsite_builder\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Counts base fields of entities.
 *
 * @EntityType(
 *   id = "entity_type_base",
 *   label = @Translation("Entity type base")
 * )
 */
class EntityTypeBase extends PluginBase implements EntityTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function createBundle(string $bundle_id, array $bundle_config): ConfigEntityBundleBase {
    $entity_definition = $this->entityTypeManager->getDefinition($this->configuration['entity_type']);

    $bundle_entity = $this->entityTypeManager->getStorage($entity_definition->getBundleEntityType())->create($this->getBundleConfig($bundle_id, $bundle_config));
    $bundle_entity->save();

    return $bundle_entity;
  }

  /**
   * Returns a specific bundle config.
   *
   * @param string $bundle_id
   *   The id of the new bundle.
   * @param array $bundle_config
   *   Additional bundle config.
   *
   * @return array
   *   An array of bundle config.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getBundleConfig($bundle_id, array $bundle_config) {
    $entity_definition = $this->entityTypeManager->getDefinition($this->configuration['entity_type']);

    $bundle_definition = $this->entityTypeManager->getDefinition($entity_definition->getBundleEntityType());
    return [
      $bundle_definition->getKey('id') => $bundle_id,
      $bundle_definition->getKey('label') => $bundle_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(ConfigEntityBundleBase $bundle, array $bundle_config): void {
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $bundle_config) : bool {
    return TRUE;
  }

}
