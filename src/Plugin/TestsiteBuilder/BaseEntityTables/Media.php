<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\BaseEntityTables;

/**
 * Media type plugin.
 *
 * @TestsiteBuilderBaseEntityTables(
 *   id = "media",
 *   label = @Translation("Media"),
 *   description = @Translation("Base entity tables plugin for medias.")
 * )
 */
class Media extends Generic {

  /**
   * {@inheritdoc}
   */
  public function alterRowTemplate(array &$row_template, array $entity_state_info) {
    $contentCreatorStorage = $entity_state_info['content_creator_storage'];

    foreach (['media_field_data', 'media_field_revision'] as $table_name) {
      if (isset($row_template[$table_name])) {
        $thumbnail = $contentCreatorStorage->getSampleDataType('image');
        $row_template[$table_name]['thumbnail__target_id'] = $thumbnail['target_id'];
        $row_template[$table_name]['thumbnail__alt'] = $thumbnail['alt'];
        $row_template[$table_name]['thumbnail__title'] = $thumbnail['title'];
        $row_template[$table_name]['thumbnail__width'] = $thumbnail['width'];
        $row_template[$table_name]['thumbnail__height'] = $thumbnail['height'];
      }
    }
  }

}
