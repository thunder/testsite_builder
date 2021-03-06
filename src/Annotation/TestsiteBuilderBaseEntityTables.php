<?php

namespace Drupal\testsite_builder\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a base entity tables plugin item annotation object.
 *
 * @see \Drupal\testsite_builder\BaseEntityTablesPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class TestsiteBuilderBaseEntityTables extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
