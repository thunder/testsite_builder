<?php

namespace Drupal\testsite_builder\Events;

/**
 * Config creator events.
 *
 * @package Drupal\testsite_builder\Events
 */
final class ConfigCreatorEvents {

  /**
   * The name of the event triggered when a new entity type is created.
   *
   * @Event
   *
   * @var string
   */
  const ENTITY_BUNDLE_CREATE = 'testsite_builder.config_creator.entity_bundle_create';

  /**
   * The name of the event triggered when a new field is created.
   *
   * @Event
   *
   * @var string
   */
  const FIELD_CREATE = 'testsite_builder.config_creator.field_create';

}
