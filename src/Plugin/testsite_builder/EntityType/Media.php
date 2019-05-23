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
  public function setMediaSourceManager(MediaSourceManager $mediaSourceManager): void {
    $this->mediaSourceManager = $mediaSourceManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundleConfig($bundle_id, array $bundle_config) {
    $config = parent::getBundleConfig($bundle_id, $bundle_config);
    $config['source'] = $bundle_config['source'];

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $bundle_config): bool {
    if (!parent::isApplicable($bundle_config)) {
      return FALSE;
    }
    return !empty($this->mediaSourceManager->getDefinition($bundle_config['source'], FALSE));
  }

}
