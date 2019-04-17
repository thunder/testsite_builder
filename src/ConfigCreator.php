<?php

namespace Drupal\testsite_builder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;

/**
 * The ConfigCreator class.
 *
 * @package Drupal\sampler
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
      $createdFields = [];
      if (!isset($this->reportData[$entity_type]['bundle'])) {
        continue;
      }
      foreach ($this->reportData[$entity_type]['bundle'] as $id => $bundle) {
        $bundle_definition = $this->entityTypeManager->getDefinition($entity_definition->getBundleEntityType());

        // Create bundle.
        $bundle_entity = $this->entityTypeManager->getStorage($entity_definition->getBundleEntityType())->create([
          $bundle_definition->getKey('id') => $id,
          $bundle_definition->getKey('label') => $id,
        ]);
        $bundle_entity->save();

        // Create fields.
        foreach ($bundle['fields'] as $field_type => $count) {
          if (!isset($fieldTypeDefinitions[$field_type])) {
            continue;
          }

          for ($i = 0; $i < $count; $i++) {
            $fieldName = $field_type . '_' . $i;
            if (!in_array($fieldName, $createdFields)) {
              $field_storage = $this->entityTypeManager->getStorage('field_storage_config')->create([
                'field_name' => $fieldName,
                'entity_type' => $entity_type,
                'type' => $field_type,
              ]);
              $field_storage->save();
              $createdFields[] = $fieldName;
            }
            $field_instance = $this->entityTypeManager->getStorage('field_config')->create([
              'field_name' => $fieldName,
              'entity_type' => $entity_type,
              'type' => $field_type,
              'bundle' => $bundle_entity->id(),
            ]);
            $field_instance->save();
          }
        }
      }
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
