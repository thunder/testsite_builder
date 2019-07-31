<?php

namespace Drupal\testsite_builder\Plugin\testsite_builder\FieldType;

/**
 * ListString field type.
 *
 * @FieldType(
 *   id = "list_string",
 *   label = @Translation("ListString")
 * )
 */
class ListString extends FieldTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldStorageConfig(array $instance): array {
    $config = parent::getFieldStorageConfig($instance);

    $config['settings']['allowed_values'] = array_map(function ($option_index) {
      return "Option {$option_index}";
    }, range(0, 9));
    $config['settings']['allowed_values_function'] = '';

    return $config;
  }

}
