<?php

namespace Drupal\testsite_builder\Events;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\testsite_builder\ContentCreatorStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The content creator entity subscriber.
 *
 * @package Drupal\testsite_builder\Events
 */
class ContentCreatorSubscriber implements EventSubscriberInterface {

  /**
   * The content creator config storage service.
   *
   * @var \Drupal\testsite_builder\ContentCreatorStorage
   */
  protected $contentCreatorStorage;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContentCreatorEntitySubscriber constructor.
   *
   * @param \Drupal\testsite_builder\ContentCreatorStorage $content_creator_storage
   *   The content creator storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   */
  public function __construct(ContentCreatorStorage $content_creator_storage, EntityTypeManagerInterface $entity_type_manager) {
    $this->contentCreatorStorage = $content_creator_storage;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigCreatorEvents::ENTITY_BUNDLE_CREATE => [
        ['onEntityBundleCreate', 10],
      ],
      ConfigCreatorEvents::FIELD_CREATE => [
        ['onFieldCreate', 10],
      ],
    ];
  }

  /**
   * Handles on entity create event.
   *
   * @param \Drupal\testsite_builder\Events\ConfigCreatorEntityBundleCreateEvent $event
   *   Config creator entity event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onEntityBundleCreate(ConfigCreatorEntityBundleCreateEvent $event) {
    $bundle_config = $event->getBundleConfig();
    $entity_type = $bundle_config->getEntityType()->getBundleOf();

    if (!$this->contentCreatorStorage->hasConfig([$entity_type])) {
      $this->addBaseEntityConfig($bundle_config);
    }

    $bundle_type = $bundle_config->id();
    if (empty($bundle_type)) {
      return;
    }

    $sampler_bundle_config = $event->getSamplerBundleConfig();

    $this->contentCreatorStorage->addConfig(
      [$entity_type, '_bundles', $bundle_type],
      [
        '_type' => 'bundle',
        'name' => $bundle_type . ' (' . $entity_type . ')',
        'type' => $bundle_type,
        'entity_type' => $entity_type,
        'instances' => $sampler_bundle_config['instances'],
        '_fields' => [],
      ]
    );

  }

  /**
   * Adds base entity type information.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_config
   *   The bundle configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addBaseEntityConfig(ConfigEntityInterface $bundle_config) {
    $entity_type = $bundle_config->getEntityType()->getBundleOf();

    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);

    $this->contentCreatorStorage->addConfig(
      [$entity_type],
      [
        '_type' => 'entity',
        '_entity_definition_keys' => $entity_definition->getKeys(),
        '_base_tables' => [],
        'name' => $entity_type,
      ]
    );

    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $this->entityTypeManager->getStorage($entity_type)
      ->getTableMapping();

    foreach ($table_mapping->getTableNames() as $table_name) {
      $this->contentCreatorStorage->addConfig(
        [$entity_type, '_base_tables', $table_name],
        [
          '_type' => 'table',
          '_columns' => $table_mapping->getAllColumns($table_name),
          'name' => $table_name,
        ]
      );
    }
  }

  /**
   * Handles config create field create event.
   *
   * @param \Drupal\testsite_builder\Events\ConfigCreatorFieldCreateEvent $event
   *   The config create field create event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function onFieldCreate(ConfigCreatorFieldCreateEvent $event) {
    $field_config = $event->getFieldConfig();
    $sampler_field_config = $event->getSamplerFieldConfig();

    $entity_type = $field_config->getTargetEntityTypeId();
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);

    $bundle_type = $field_config->getTargetBundle();
    $field_name = $field_config->getName();
    $field_type = $field_config->getType();

    $table_mapping = $this->entityTypeManager->getStorage($entity_type)
      ->getTableMapping();
    $field_table_name = $table_mapping->getFieldTableName($field_name);

    $this->contentCreatorStorage->addConfig(
      [$entity_type, '_bundles', $bundle_type, '_fields', $field_name],
      [
        '_type' => 'field',
        'name' => $field_name . ' (' . $entity_type . ')',
        'entity_type' => $entity_type,
        'field_name' => $field_name,
        'field_type' => $field_type,
        '_table' => [
          '_type' => 'table',
          'name' => $field_table_name,
        ],
      ]
    );

    if ($entity_definition->isRevisionable()) {
      $field_revision_table_name = $table_mapping->getDedicatedRevisionTableName($field_config->getFieldStorageDefinition());
      $this->contentCreatorStorage->addConfig(
        [
          $entity_type,
          '_bundles',
          $bundle_type,
          '_fields',
          $field_name,
          '_rev_table',
        ],
        [
          '_type' => 'table',
          'name' => $field_revision_table_name,
        ]
      );
    }

    if ($field_type === 'entity_reference' || $field_type === 'entity_reference_revisions') {
      $bundle_field_info = [
        'reference' => $field_type,
        'target_type' => $field_config->getSetting('target_type'),
        'target_bundles' => array_flip($sampler_field_config['target_bundles']),
        'histogram' => $sampler_field_config['histogram'],
      ];

      $this->contentCreatorStorage->addConfig(
        [
          $entity_type,
          '_bundles',
          $bundle_type,
          '_fields',
          $field_name,
          '_bundle_info',
        ],
        $bundle_field_info
      );
    }

    if ($field_type !== 'entity_reference' && $field_type !== 'entity_reference_revisions' && !$this->contentCreatorStorage->hasSampleData($field_type)) {
      $this->createSampleDataForField($field_config);
    }
  }

  /**
   * Generate sample data for field type.
   *
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The field configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createSampleDataForField(FieldConfigInterface $field_config) {
    /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin */
    $field_type_plugin = \Drupal::service('plugin.manager.field.field_type');

    /** @var \Drupal\Core\Field\FieldItemBase $data_generator */
    $data_generator = $field_type_plugin->createInstance($field_config->getType(), [
      'field_definition' => $field_config,
      'name' => $field_config->getTypedData()->getName(),
      'parent' => $field_config->getTypedData()->getParent(),
    ]);

    $samples = [];
    for ($i = 0; $i < 5; $i++) {
      $samples[] = $data_generator->generateSampleValue($field_config);
    }

    $samples = array_filter($samples);
    if (!empty($samples)) {
      $this->contentCreatorStorage->addSampleData($field_config->getType(), $samples);
    }
  }

}
