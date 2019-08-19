<?php

namespace Drupal\testsite_builder\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a config importer plugin annotation object.
 *
 * @see \Drupal\testsite_builder\ConfigImporterPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class TestsiteBuilderConfigImporter extends Plugin {

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
