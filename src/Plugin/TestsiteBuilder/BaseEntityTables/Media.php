<?php

namespace Drupal\testsite_builder\Plugin\TestsiteBuilder\BaseEntityTables;

use Drupal\testsite_builder\ContentCreatorStorage;

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
  public function alterRow(array &$row, $table_name, ContentCreatorStorage $content_creator_storage) {
    if ($table_name != 'media_field_data' && $table_name != 'media_field_revision') {
      return;
    }

    $thumbnail = $content_creator_storage->getModSampleDataType('image', $row['mid']);

    $row['thumbnail__target_id'] = $thumbnail['target_id'];
    $row['thumbnail__alt'] = $thumbnail['alt'];
    $row['thumbnail__title'] = $thumbnail['title'];
    $row['thumbnail__width'] = $thumbnail['width'];
    $row['thumbnail__height'] = $thumbnail['height'];
  }

}
