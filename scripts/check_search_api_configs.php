<?php

/**
 * @file
 * Helper script to check Search API configuration.
 *
 * Use it with: drush scr
 * -> Be cause we expect Drupal to be bootstrapped.
 */

use Drupal\block\Entity\Block;
use Drupal\facets\Entity\Facet;
use Drupal\search_api\Entity\Index;
use Drupal\views\Entity\View;

echo 'Checking Search API configurations...' . PHP_EOL . PHP_EOL;

/* @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
$config_factory = Drupal::configFactory();

/* @var \Drupal\search_api\Entity\Index $search_index */
foreach (Index::loadMultiple() as $search_index) {
  // Start with Search API index check.
  echo "-> Checking index ID: {$search_index->id()}" . PHP_EOL;
  $index_view_base_table = 'search_api_index_' . $search_index->id();

  // -> find related view.
  $index_view = NULL;
  $index_view_config = NULL;

  /* @var \Drupal\views\Entity\View $view */
  foreach (View::loadMultiple() as $view) {
    $view_config = $config_factory->get("views.view.{$view->id()}");
    if ($view_config->get('base_table') === $index_view_base_table) {
      echo "Found View ID: {$view->id()}" . PHP_EOL;

      $index_view = $view;
      $index_view_config = $view_config;

      break;
    }
  }

  // WARN: no view!
  if ($index_view === NULL) {
    echo "==> Unable to find view for index: {$search_index->id()}" . PHP_EOL;
  }

  // Check all base tables in fields and filters.
  foreach ($view_config->get('display.default.display_options.filters') as $filter_id => $filter_config) {
    if ($filter_config['table'] !== $index_view_base_table) {
      echo "==> Wrong table name for Filter: {$filter_id}" . PHP_EOL;
    }
  }

  foreach ($view_config->get('display.default.display_options.fields') as $field_id => $field_config) {
    // Skip fields that are not using Search API index as a source.
    if (in_array($field_id, [
      'views_bulk_operations_bulk_form',
      'search_api_operations',
      'field_teaser_media',
    ])) {
      continue;
    }

    if ($field_config['table'] !== $index_view_base_table) {
      echo "==> Wrong table name for Field: {$field_id}" . PHP_EOL;
    }
  }

  // -> find block related to view.
  $block_plugin_id = "views_exposed_filter_block:{$view->id()}-page_1";
  $exposed_view_block = NULL;

  /* @var \Drupal\block\Entity\Block[] $blocks */
  $blocks = Block::loadMultiple();
  foreach ($blocks as $block) {
    if ($block->getPluginId() === $block_plugin_id) {
      echo "Found block ID: {$block->id()}" . PHP_EOL;

      $exposed_view_block = $block;

      break;
    }
  }

  // WARN: no exposed form block!
  if ($exposed_view_block === NULL) {
    echo '==> Exposed form block is not found!' . PHP_EOL;
  }

  // Check block config.
  $block_settings = $block->get('settings');
  if ($block_settings['id'] !== $block_plugin_id) {
    echo '==> Wrong block settings ID!' . PHP_EOL;
  }

  // -> find facets related to view.
  $facet_source_id = "search_api:views_page__{$view->id()}__page_1";
  $view_related_facets = [];

  /* @var \Drupal\facets\Entity\Facet[] $facets */
  $facets = Facet::loadMultiple();
  foreach ($facets as $facet) {
    if ($facet->getFacetSourceId() === $facet_source_id) {
      $view_related_facets[] = $facet;
    }
  }

  // WARN: no facets!
  if (empty($view_related_facets)) {
    echo '==> No facets found related to view!' . PHP_EOL;
  }

  $num_of_facets = count($view_related_facets);
  echo "Number of facets found: {$num_of_facets}" . PHP_EOL;

  // -> find block related to facets.
  /* @var \Drupal\block\Entity\Block[] $view_related_facet_blocks */
  $view_related_facet_blocks = [];
  foreach ($view_related_facets as $view_related_facet) {
    $facet_block_plugin_id = "facet_block:{$view_related_facet->id()}";

    foreach ($blocks as $block) {
      if ($block->getPluginId() === $facet_block_plugin_id) {
        $view_related_facet_blocks[] = $block;

        continue 2;
      }
    }

    echo "==> No block found for facet: {$view_related_facet->id()}" . PHP_EOL;
  }

  // WARN: no facet blocks!
  if (empty($view_related_facet_blocks)) {
    echo '==> No facet blocks found!' . PHP_EOL;
  }

  $num_of_facet_blocks = count($view_related_facet_blocks);
  echo "Number of facet blocks found: {$num_of_facet_blocks}" . PHP_EOL;

  // Check facet blocks config.
  foreach ($view_related_facet_blocks as $view_related_facet_block) {
    $view_related_facet_block_settings = $view_related_facet_block->get('settings');
    if ($view_related_facet_block_settings['id'] !== $view_related_facet_block->getPluginId()) {
      echo "==> Wrong facet block settings ID for block: {$block->id()}" . PHP_EOL;
    }

    if ($view_related_facet_block_settings['block_id'] !== $view_related_facet_block->id()) {
      echo "==> Wrong facet block settings Block_ID for block: {$block->id()}" . PHP_EOL;
    }
  }
}
