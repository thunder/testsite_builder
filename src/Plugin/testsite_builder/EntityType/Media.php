<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\EntityType;

use Drupal\media\MediaSourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Counts base fields of entities.
 *
 * @EntityType(
 *   id = "media",
 *   label = @Translation("Entity type base")
 * )
 */
class Media extends EntityTypeBase {

  /**
   * The media source manager.
   *
   * @var \Drupal\media\MediaSourceManager
   */
  protected $mediaSourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $testbuilder_entity_type = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $testbuilder_entity_type->setMediaSourceManager($container->get('plugin.manager.media.source'));
    return $testbuilder_entity_type;
  }

  /**
   * Sets the media source manager.
   *
   * @param \Drupal\media\MediaSourceManager $mediaSourceManager
   *   The media source manager.
   */
  protected function setMediaSourceManager(MediaSourceManager $mediaSourceManager): void {
    $this->mediaSourceManager = $mediaSourceManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundleConfig($bundle_id, array $bundle_config) {
    $config = parent::getBundleConfig($bundle_id, $bundle_config);
    $config['source'] = $bundle_config['source']['plugin_id'];

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function postCreateBundle(string $bundle_id, array $bundle_config, array $created_fields): void {
    /** @var \Drupal\media\MediaTypeInterface $type */
    $type = $this->entityTypeManager->getStorage('media_type')->load($bundle_id);

    $config = $type->getSource()->getConfiguration();
    $key = $bundle_config['source']['source_field_index'];
    $config['source_field'] = $created_fields[$key]->getName();

    $type->getSource()->setConfiguration($config);
    $type->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $bundle_config): bool {
    if (!parent::isApplicable($bundle_config)) {
      return FALSE;
    }
    return !empty($this->mediaSourceManager->getDefinition($bundle_config['source']['plugin_id'], FALSE));
  }

}
