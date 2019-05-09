<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

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
   * The field type plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * The content creator config file.
   *
   * @var string
   */
  protected $contentCreatorFile;

  /**
   * The content creator configuration array.
   *
   * @var string
   */
  protected $contentCreatorConfig = [];

  /**
   * The sampled data types file.
   *
   * @var string
   */
  protected $sampledDataFile;

  /**
   * The content creator configuration array.
   *
   * @var string
   */
  protected $sampledData = [];

  /**
   * Constructs a new ConfigCreator object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FieldTypePluginManagerInterface $fieldTypePluginManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
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
   * Sets content creator config file.
   *
   * @param string $filename
   *   The file name for content creator config.
   */
  public function setContentCreatorFile($filename) {
    $this->contentCreatorFile = $filename;
  }

  /**
   * Sets sampled data types file.
   *
   * @param string $filename
   *   The file name to output sampled data types.
   */
  public function setSampledDataFile($filename) {
    $this->sampledDataFile = $filename;
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

      if ($definition->getBundleEntityType()) {
        // Delete all entities.
        $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
        $this->entityTypeManager->getStorage($entity_type)->delete($entities);

        // Delete all bundles.
        $bundles = $this->entityTypeManager->getStorage($definition->getBundleEntityType())->loadMultiple();
        $this->entityTypeManager->getStorage($definition->getBundleEntityType())->delete($bundles);

        // Delete fields.
        $entities = $this->entityTypeManager->getStorage('field_config')->loadByProperties(['entity_type' => $entity_type]);
        $this->entityTypeManager->getStorage('field_config')->delete($entities);

        $entities = $this->entityTypeManager->getStorage('field_storage_config')->loadByProperties(['entity_type' => $entity_type]);
        $this->entityTypeManager->getStorage('field_storage_config')->delete($entities);
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
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function create(): ConfigCreator {
    $fieldTypeDefinitions = $this->fieldTypePluginManager->getDefinitions();

    foreach ($this->getEntityTypes() as $entity_type) {
      $entity_definition = $this->entityTypeManager->getDefinition($entity_type);

      // Store configuration for content creator.
      if (isset($this->contentCreatorFile)) {
        /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
        $table_mapping = $this->entityTypeManager->getStorage($entity_type)
          ->getTableMapping();

        // Entity -> Base Table !!!
        $base_table = $entity_definition->getBaseTable();
        $this->contentCreatorConfig[$entity_type] = [
          '_type' => 'entity',
          '_entity_definition_keys' => $entity_definition->getKeys(),
          '_base_tables' => [
            $base_table => [
              '_type' => 'table',
              '_columns' => $table_mapping->getAllColumns($base_table),
              'name' => $base_table,
            ],
          ],
          'name' => $entity_type,
        ];

        if ($entity_definition->isRevisionable()) {
          $rev_base_table = $entity_definition->getRevisionTable();

          $this->contentCreatorConfig[$entity_type]['_base_tables'][$rev_base_table] = [
            '_type' => 'table',
            '_columns' => $table_mapping->getAllColumns($rev_base_table),
            'name' => $rev_base_table,
          ];
        }

        $data_table = $entity_definition->getDataTable();
        if ($data_table) {
          $this->contentCreatorConfig[$entity_type]['_base_tables'][$data_table] = [
            '_type' => 'table',
            '_columns' => $table_mapping->getAllColumns($data_table),
            'name' => $data_table,
          ];

          if ($entity_definition->isRevisionable()) {
            $rev_data_table = $entity_definition->getRevisionDataTable();

            $this->contentCreatorConfig[$entity_type]['_base_tables'][$rev_data_table] = [
              '_type' => 'table',
              '_columns' => $table_mapping->getAllColumns($rev_data_table),
              'name' => $rev_data_table,
            ];
          }
        }
      }

      $createdFields = [];
      if (!isset($this->reportData[$entity_type]['bundle'])) {
        continue;
      }
      foreach ($this->reportData[$entity_type]['bundle'] as $bundle_type => $bundle) {
        $bundle_definition = $this->entityTypeManager->getDefinition($entity_definition->getBundleEntityType());

        // Create bundle.
        $bundle_entity = $this->entityTypeManager->getStorage($entity_definition->getBundleEntityType())->create([
          $bundle_definition->getKey('id') => $bundle_type,
          $bundle_definition->getKey('label') => $bundle_type,
        ]);
        $bundle_entity->save();

        $form_display = entity_get_form_display($entity_type, $bundle_entity->id(), 'default');

        if (isset($this->contentCreatorFile)) {
          $this->contentCreatorConfig[$entity_type]['_bundles'][$bundle_type] = [
            '_type' => 'bundle',
            'name' => $bundle_type . ' (' . $entity_type . ')',
            'type' => $bundle_type,
            'entity_type' => $entity_type,
            'instances' => $bundle['instances'],
          ];
        }

        // Create fields.
        foreach ($bundle['fields'] as $field_type => $count) {
          if (!isset($fieldTypeDefinitions[$field_type])) {
            continue;
          }

          if (in_array($field_type, ['entity_reference', 'entity_reference_revisions'])) {
            $count = array_sum($count);
          }

          for ($i = 0; $i < $count; $i++) {
            $field_name = $field_type . '_' . $i;
            if (!in_array($field_name, $createdFields)) {
              $field_storage_config = [
                'field_name' => $field_name,
                'entity_type' => $entity_type,
                'type' => $field_type,
                'settings' => [],
              ];

              switch ($field_type) {
                case 'entity_reference_revisions':
                  $field_storage_config['settings']['target_type'] = 'paragraph';
                  $field_storage_config['cardinality'] = -1;
                  break;

                case 'entity_reference':
                  $field_storage_config['settings']['target_type'] = reset(array_keys($bundle['fields'][$field_type]));
                  $field_storage_config['cardinality'] = -1;
                  break;
              }

              /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
              $field_storage = $this->entityTypeManager->getStorage('field_storage_config')
                ->create($field_storage_config);
              $field_storage->save();
              $createdFields[] = $field_name;
            }

            $field_instance_config = [
              'field_name' => $field_name,
              'entity_type' => $entity_type,
              'type' => $field_type,
              'bundle' => $bundle_entity->id(),
              'settings' => [],
            ];

            switch ($field_type) {
              case 'entity_reference_revisions':
                $field_instance_config['settings']['handler'] = 'default:paragraph';
                $field_instance_config['settings']['handler_settings']['negate'] = 1;
                break;
            }

            /** @var \Drupal\field\FieldConfigInterface $field_instance */
            $field_instance = $this->entityTypeManager->getStorage('field_config')
              ->create($field_instance_config);
            $field_instance->save();

            $field_type_definition = $this->fieldTypePluginManager->getDefinition($field_instance->getFieldStorageDefinition()
              ->getType());
            $form_display->setComponent($field_name, ['type' => $field_type_definition['default_widget']]);

            if (isset($this->contentCreatorFile)) {
              $table_mapping = $this->entityTypeManager->getStorage($entity_type)
                ->getTableMapping();
              $field_table_name = $table_mapping->getFieldTableName($field_name);
              $this->contentCreatorConfig[$entity_type]['_bundles'][$bundle_type]['_fields'][$field_name] = [
                '_type' => 'field',
                'name' => $field_name . ' (' . $entity_type . ')',
                'entity_type' => $entity_type,
                'field_name' => $field_name,
                'field_type' => $field_type,
                '_table' => [
                  '_type' => 'table',
                  'name' => $field_table_name,
                ],
              ];

              if ($entity_definition->isRevisionable()) {
                $field_revision_table_name = $table_mapping->getDedicatedRevisionTableName($field_instance->getFieldStorageDefinition());

                $this->contentCreatorConfig[$entity_type]['_bundles'][$bundle_type]['_fields'][$field_name]['_rev_table'] = [
                  '_type' => 'table',
                  'name' => $field_revision_table_name,
                ];
              }

              $bundle_field_info = [];
              if ($field_type === 'entity_reference_revisions') {
                $bundle_field_info['reference'] = 'entity_reference_revisions';
                $bundle_field_info['target_type'] = 'paragraph';

                if (!empty($this->reportData[$entity_type]['histogram']['paragraph'])) {
                  $bundle_field_info['histogram'] = json_encode($this->reportData[$entity_type]['histogram']['paragraph']);
                }
              }

              if ($field_type === 'entity_reference') {
                $bundle_field_info['reference'] = 'entity_reference';
                $bundle_field_info['target_type'] = $field_instance->getSetting('target_type');

                if ($bundle_field_info['target_type'] === 'media') {
                  $bundle_field_info['histogram'] = json_encode(['1' => $bundle['instances']]);
                }
              }

              if ($field_type === 'entity_reference' || $field_type === 'entity_reference_revisions') {
                $this->contentCreatorConfig[$entity_type]['_bundles'][$bundle_type]['_fields'][$field_name]['_bundle_info'] = $bundle_field_info;
              }
            }

            if (isset($this->sampledDataFile)) {
              if ($field_type !== 'entity_reference' && $field_type !== 'entity_reference_revisions' && !isset($this->sampledData[$field_type])) {
                /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin */
                $field_type_plugin = \Drupal::service('plugin.manager.field.field_type');

                /** @var \Drupal\Core\Field\FieldItemBase $sampler */
                $sampler = $field_type_plugin->createInstance($field_instance->getType(), [
                  'field_definition' => $field_instance,
                ]);

                $samples = [];
                for ($i = 0; $i < 5; $i++) {
                  $samples[] = $sampler->generateSampleValue($field_instance);
                }

                $samples = array_filter($samples);
                if (!empty($samples)) {
                  $this->sampledData[$field_type] = $samples;
                }
              }
            }
          }
        }

        $form_display->save();
      }
    }

    // Store created data for content creator.
    if (isset($this->contentCreatorFile)) {
      file_put_contents($this->contentCreatorFile, json_encode($this->contentCreatorConfig, JSON_PRETTY_PRINT));
    }

    if (isset($this->sampledDataFile)) {
      file_put_contents($this->sampledDataFile, json_encode($this->sampledData, JSON_PRETTY_PRINT));
    }

    return $this;
  }

  /**
   * Get a list of all supported entity types.
   *
   * @return array
   *   List of supported entity types.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
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
    ]);
  }

}
