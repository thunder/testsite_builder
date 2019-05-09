<?php

namespace Drupal\testsite_builder;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base BaseEntityTables.
 */
abstract class BaseEntityTablesBase extends PluginBase implements BaseEntityTablesInterface {

  /**
   * Base table row data.
   *
   * @var array
   */
  protected static $baseTableRowData = [
    'langcode' => 'en',
    'uid' => 1,
    'published' => 1,
    'default_langcode' => 1,
    'revision_translation_affected' => 1,
    'revision_uid' => 0,
    'revision_default' => 1,
  ];

}
