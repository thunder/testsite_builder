<?php

namespace Drupal\testsite_builder;

use Drupal\Core\Database\Connection;

/**
 * Class ContentCreator.
 *
 * *** IMPORTANT ***
 * - we need GRANT:
 *    GRANT FILE on *.* to 'drupaluser'@'localhost'
 * - we need disabled "secure-file-priv":
 *    [mysqld]
 *    secure-file-priv=""
 *
 * TODO:
 *   - add logging (fastest solution is "error_log")
 *
 * @package Drupal\testsite_builder
 */
class ContentCreator {

  /**
   * Base table keys in provided ContentCreator config.
   *
   * @var array
   */
  protected static $entityBaseTableKeys = [
    '_base_table',
    '_rev_base_table',
    '_data_table',
    '_rev_data_table',
  ];

  /**
   * Field table template.
   *
   * @var array
   */
  protected static $fieldTableTemplates = [
    'bundle' => '',
    'deleted' => 0,
    'entity_id' => 0,
    'revision_id' => 0,
    'langcode' => 'en',
    'delta' => 0,
  ];

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

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Configuration for ContentCreator.
   *
   * This is main source of information to generate CSV files.
   *
   * @var array
   */
  protected $config = [];

  /**
   * List of sampled data types with generated data.
   *
   * @var array
   */
  protected $sampledDataTypes = [];

  /**
   * Output directory for created CSV files.
   *
   * @var string
   */
  protected $outputDirectory = '';

  /**
   * Cache of number of bundle instances for entity type.
   *
   * @var array
   */
  protected $cacheBundleInstances = [];

  /**
   * Cache of field definitions for bundle.
   *
   * @var array
   */
  protected $cacheBundleFieldDefinition = [];

  /**
   * Cache of reference field defintions for bundle.
   *
   * @var array
   */
  protected $cacheBundleReferencedFieldDefinition = [];

  /**
   * Keeps open CSV file handlers.
   *
   * @var array
   */
  protected $cacheCsvFileHandlers = [];

  /**
   * Keeps row template for base entity tables.
   *
   * @var array
   */
  protected $cacheBaseTableRowTemplates = [];

  /**
   * Keeps track of information relation to entity.
   *
   * @var array
   */
  protected $globalState = [];

  /**
   * Keeps stack of entity referencing each other, to avoid infinite loop.
   *
   * @var array
   */
  protected $entityTypeReferenceNestingStack = [];

  /**
   * List of entity types to create.
   *
   * @var array
   */
  protected $baseEntityTypes = [
    'node',
  ];

  /**
   * Constructs a new ContentCreator object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Sets configuration for content creator.
   *
   * @param array $config
   *   The content creator configuration created during configuration create.
   */
  public function setConfig(array $config) {
    $this->config = $config;
  }

  /**
   * Sets sampled data types.
   *
   * @param array $sampled_data
   *   The sampled data types created during configuration creation.
   */
  public function setSampledData(array $sampled_data) {
    $this->sampledDataTypes = $sampled_data;
  }

  /**
   * Sets output directory for CSV files.
   *
   * @param string $directory
   *   The output directory for CSV files.
   */
  public function setOutputDirectory($directory) {
    $this->outputDirectory = realpath($directory);
  }

  /**
   * Get sample data used to generate database entries.
   *
   * TODO: We call this a lot. Investigate if performance can be improved.
   *
   * @param string $type
   *   The data type.
   * @param bool $random
   *   Flag if random value should be returned or not.
   *
   * @return mixed
   *   Returns sampled data.
   */
  protected function getSampledData($type, $random = TRUE) {
    if (!isset($this->sampledDataTypes[$type])) {
      return [];
    }

    if (!$random) {
      return $this->sampledDataTypes[$type][0];
    }

    return $this->sampledDataTypes[$type][mt_rand(0, count($this->sampledDataTypes[$type]) - 1)];
  }

  /**
   * Init global state.
   */
  protected function initGlobalState() {
    $this->globalState = [
      'entity_index' => 0,
      'bundle_index' => 0,
      'entity_states' => [],
    ];
  }

  /**
   * Init caching values for entity type.
   *
   * @param string $entity_type
   *   The entity type.
   */
  protected function initEntityState($entity_type) {
    if (isset($this->globalState['entity_states'][$entity_type])) {
      return;
    }

    $this->globalState['entity_states'][$entity_type]['count'] = 1;
    $this->globalState['entity_states'][$entity_type]['index'] = $this->globalState['entity_index'];
    $this->globalState['entity_index']++;
  }

  /**
   * Init caching values for bundle type.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   */
  protected function initBundleState($entity_type, $bundle_type) {
    if (isset($this->globalState['entity_states'][$entity_type][$bundle_type])) {
      return;
    }

    $this->globalState['entity_states'][$entity_type][$bundle_type]['index'] = $this->globalState['bundle_index'];
    $this->globalState['bundle_index']++;
  }

  /**
   * Entry function to create CSV files with data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createCsvFiles() {
    $this->initGlobalState();
    foreach ($this->baseEntityTypes as $entity_type) {
      $this->initEntityState($entity_type);

      foreach ($this->config[$entity_type]['_bundles'] as $bundle_type => $bundle) {
        $this->initBundleState($entity_type, $bundle_type);

        $unique_bundle_key = $entity_type . '_' . $bundle_type;
        if (isset($this->entityTypeReferenceNestingStack[$unique_bundle_key])) {
          continue;
        }
        $this->entityTypeReferenceNestingStack[$unique_bundle_key] = TRUE;

        // TODO: TESTING!!!
        // $instances = 100000; // more data.
        $this->createBundle($entity_type, $bundle_type, $bundle['instances']);

        unset($this->entityTypeReferenceNestingStack[$unique_bundle_key]);
      }

      // Close all CSV files!
      foreach ($this->cacheCsvFileHandlers as $entity_type => $csvFiles) {
        foreach ($csvFiles as $csvFile) {
          fclose($csvFile);
        }
      }
    }
  }

  /**
   * Initialize base table CSV files and other cached values.
   *
   * @param string $entity_type
   *   The entity type.
   */
  protected function initBaseEntityTables($entity_type) {
    if (isset($this->cacheCsvFileHandlers[$entity_type])) {
      return;
    }

    foreach (static::$entityBaseTableKeys as $base_table_type) {
      if (!isset($this->config[$entity_type][$base_table_type])) {
        continue;
      }

      $base_table_name = $this->config[$entity_type][$base_table_type]['name'];
      $this->cacheCsvFileHandlers[$entity_type][$base_table_name] = fopen($this->outputDirectory . '/' . $base_table_name . '.csv', 'w');

      fputcsv($this->cacheCsvFileHandlers[$entity_type][$base_table_name], $this->config[$entity_type][$base_table_type]['_columns']);
    }
  }

  /**
   * Return row templates for base tables.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return array
   *   Returns row templates for base tables.
   */
  protected function getBaseTableTemplates($entity_type) {
    if (isset($this->cacheBaseTableRowTemplates[$entity_type])) {
      return $this->cacheBaseTableRowTemplates[$entity_type];
    }

    $column_mapping_reverse = array_flip($this->config[$entity_type]['_entity_definition_keys']);

    $row_templates = [];
    foreach (static::$entityBaseTableKeys as $table_type) {
      $table_name = $this->config[$entity_type][$table_type]['name'];

      $row_templates[$table_name] = [];
      foreach ($this->config[$entity_type][$table_type]['_columns'] as $column) {
        if (isset($column_mapping_reverse[$column]) && array_key_exists($column_mapping_reverse[$column], static::$baseTableRowData)) {
          $row_templates[$table_name][$column] = static::$baseTableRowData[$column_mapping_reverse[$column]];

          continue;
        }

        if (isset(static::$baseTableRowData[$column])) {
          $row_templates[$table_name][$column] = static::$baseTableRowData[$column];

          continue;
        }

        $row_templates[$table_name][$column] = NULL;
      }
    }

    $this->cacheBaseTableRowTemplates[$entity_type] = $row_templates;

    return $this->cacheBaseTableRowTemplates[$entity_type];
  }

  /**
   * Creates CSV file for database import.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   * @param int $num_of_instances
   *   The number of instances for bundle.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createBundle($entity_type, $bundle_type, $num_of_instances) {
    $this->initBaseEntityTables($entity_type);

    $entity_type_index = $this->globalState['entity_states'][$entity_type]['index'];
    $bundle_type_index = $this->globalState['entity_states'][$entity_type][$bundle_type]['index'];
    $entity_type_total = $this->globalState['entity_states'][$entity_type]['count'];

    $column_mapping = $this->config[$entity_type]['_entity_definition_keys'];

    // Prepare row arrays.
    $row_templates = $this->getBaseTableTemplates($entity_type);
    $variable_row_data = [
      'bundle' => $bundle_type,
    ];

    // Fill data into CSV file.
    for ($instance_index = 0; $instance_index < $num_of_instances; $instance_index++) {
      $variable_row_data['id'] = $entity_type_total;
      $variable_row_data['revision'] = $entity_type_total;
      $variable_row_data['uuid'] = sprintf("%'.08d-%'.04d-0000-0000-%'.012d", $entity_type_index, $bundle_type_index, $entity_type_total);
      $variable_row_data['label'] = $bundle_type . ' ' . $instance_index;

      foreach ($row_templates as $table_name => $row_template) {
        $row = $row_template;
        foreach ($variable_row_data as $column => $value) {
          if (isset($column_mapping[$column]) && array_key_exists($column_mapping[$column], $row)) {
            $row[$column_mapping[$column]] = $value;

            continue;
          }
        }

        fputcsv($this->cacheCsvFileHandlers[$entity_type][$table_name], array_values($row));
      }

      $entity_type_total++;
    }

    $this->globalState['entity_states'][$entity_type]['count'] = $entity_type_total;

    $this->createBundleFields($entity_type, $bundle_type, $entity_type_total - $num_of_instances, $entity_type_total);
    $this->createEntityReferenceFields($entity_type, $bundle_type, $entity_type_total - $num_of_instances, $entity_type_total);
  }

  /**
   * Bundle field definitions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   *
   * @return mixed
   *   Returns field definitions for bundle.
   */
  protected function getBundleFieldDefinitions($entity_type, $bundle_type) {
    if (isset($this->cacheBundleFieldDefinition[$entity_type][$bundle_type])) {
      return $this->cacheBundleFieldDefinition[$entity_type][$bundle_type];
    }

    $field_definitions = [];
    foreach ($this->config[$entity_type]['_bundles'][$bundle_type]['_fields'] as $field) {
      $type = $field['field_type'];
      if ($type === 'entity_reference' || $type === 'entity_reference_revisions') {
        continue;
      }

      $table_name = $field['_table']['name'];
      $rev_table_name = $field['_rev_table']['name'];
      if (!isset($this->cacheCsvFileHandlers[$entity_type][$table_name])) {
        $this->cacheCsvFileHandlers[$entity_type][$table_name] = fopen($this->outputDirectory . '/' . $table_name . '.csv', 'w');
        $this->cacheCsvFileHandlers[$entity_type][$rev_table_name] = fopen($this->outputDirectory . '/' . $rev_table_name . '.csv', 'w');

        $row = static::$fieldTableTemplates;
        $values = $this->getSampledData($type, FALSE);
        foreach ($values as $key => $value) {
          $row[$type . '_' . $key] = $value;
        }

        fputcsv($this->cacheCsvFileHandlers[$entity_type][$table_name], array_keys($row));
        fputcsv($this->cacheCsvFileHandlers[$entity_type][$rev_table_name], array_keys($row));
      }

      $field_definitions[] = [
        'type' => $type,
        'table_name' => $table_name,
        'rev_table_name' => $rev_table_name,
      ];
    }
    $this->cacheBundleFieldDefinition[$entity_type][$bundle_type] = $field_definitions;

    return $this->cacheBundleFieldDefinition[$entity_type][$bundle_type];
  }

  /**
   * Create fields for new bundle entities.
   *
   * @param string $entity_type
   *   Then entity type.
   * @param string $bundle_type
   *   The bundle type.
   * @param int $start_entity_id
   *   The starting index of created entries.
   * @param int $end_entity_id
   *   The ending index of created entries.
   */
  protected function createBundleFields($entity_type, $bundle_type, $start_entity_id, $end_entity_id) {
    $field_definitions = $this->getBundleFieldDefinitions($entity_type, $bundle_type);

    foreach ($field_definitions as $field_definition) {
      $type = $field_definition['type'];
      $table_name = $field_definition['table_name'];
      $rev_table_name = $field_definition['rev_table_name'];

      $row = static::$fieldTableTemplates;

      // TODO: Small performance improvement (kick out associative array)!
      for ($entity_id = $start_entity_id; $entity_id < $end_entity_id; $entity_id++) {
        // This is only part that has dynamic and also delta, later!!!
        $row['bundle'] = $bundle_type;
        $row['entity_id'] = $entity_id;
        $row['revision_id'] = $entity_id;

        $values = $this->getSampledData($type);
        foreach ($values as $key => $value) {
          $row[$type . '_' . $key] = $value;
        }

        $csv_row = array_values($row);
        fputcsv($this->cacheCsvFileHandlers[$entity_type][$table_name], $csv_row);
        fputcsv($this->cacheCsvFileHandlers[$entity_type][$rev_table_name], $csv_row);
      }
    }
  }

  /**
   * Get referenced filed definitions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   *
   * @return mixed
   *   Returns field definitions for bundle.
   */
  protected function getBundleReferencedFieldDefinitions($entity_type, $bundle_type) {
    if (isset($this->cacheBundleReferencedFieldDefinition[$entity_type][$bundle_type])) {
      return $this->cacheBundleReferencedFieldDefinition[$entity_type][$bundle_type];
    }

    $field_definitions = [];
    foreach ($this->config[$entity_type]['_bundles'][$bundle_type]['_fields'] as $field) {
      $type = $field['field_type'];
      if ($type !== 'entity_reference' && $type !== 'entity_reference_revisions') {
        continue;
      }

      $target_entity_type = $field['_bundle_info']['target_type'];
      if (!in_array($target_entity_type, ['media', 'paragraph'])) {
        continue;
      }

      $histogram = json_decode($field['_bundle_info']['histogram'], TRUE);
      $table_name = $field['_table']['name'];
      $rev_table_name = $field['_rev_table']['name'];
      if (!isset($this->cacheCsvFileHandlers[$entity_type][$table_name])) {
        $this->cacheCsvFileHandlers[$entity_type][$table_name] = fopen($this->outputDirectory . '/' . $table_name . '.csv', 'w');
        $this->cacheCsvFileHandlers[$entity_type][$rev_table_name] = fopen($this->outputDirectory . '/' . $rev_table_name . '.csv', 'w');

        $row = static::$fieldTableTemplates;
        $row['target_id'] = 0;
        $row['target_revision_id'] = 0;

        $csv_row = array_keys($row);
        fputcsv($this->cacheCsvFileHandlers[$entity_type][$table_name], $csv_row);
        fputcsv($this->cacheCsvFileHandlers[$entity_type][$rev_table_name], $csv_row);
      }

      $field_definitions[] = [
        'type' => $field['field_type'],
        'reference_type' => $field['_bundle_info']['reference'],
        'target_type' => $target_entity_type,
        'histogram' => $histogram,
        'table_name' => $table_name,
        'rev_table_name' => $rev_table_name,
      ];
    }
    $this->cacheBundleReferencedFieldDefinition[$entity_type][$bundle_type] = $field_definitions;

    return $this->cacheBundleReferencedFieldDefinition[$entity_type][$bundle_type];
  }

  /**
   * Create fields for new bundle entities.
   *
   * @param string $entity_type
   *   Then entity type.
   * @param string $bundle_type
   *   The bundle type.
   * @param int $start_entity_id
   *   The starting index of created entries.
   * @param int $end_entity_id
   *   The ending index of created entries.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createEntityReferenceFields($entity_type, $bundle_type, $start_entity_id, $end_entity_id) {
    $referenced_field_definitions = $this->getBundleReferencedFieldDefinitions($entity_type, $bundle_type);
    foreach ($referenced_field_definitions as $referenced_field_definition) {
      $target_entity_type = $referenced_field_definition['target_type'];
      $this->initEntityState($target_entity_type);

      $histogram = $referenced_field_definition['histogram'];
      $reference_type = $referenced_field_definition['reference_type'];
      $table_name = $referenced_field_definition['table_name'];
      $rev_table_name = $referenced_field_definition['rev_table_name'];
      for ($entity_id = $start_entity_id; $entity_id < $end_entity_id; $entity_id++) {
        $target_entity_bundle = $this->getRandomWeighted($this->getEntityBundleInstances($target_entity_type));

        // Block infinite nesting!
        $unique_bundle_key = $target_entity_type . '_' . $target_entity_bundle;

        // TODO: disable paragraph nesting!
        if ($target_entity_type === 'paragraph') {
          $unique_bundle_key = $target_entity_type;
        }
        if (isset($this->entityTypeReferenceNestingStack[$unique_bundle_key])) {
          continue;
        }
        $this->entityTypeReferenceNestingStack[$unique_bundle_key] = TRUE;

        $this->initBundleState($target_entity_type, $target_entity_bundle);
        $num_of_instances = $this->getRandomWeighted($histogram);
        $this->createBundle($target_entity_type, $target_entity_bundle, $num_of_instances);

        $end_target_entity_id = $this->globalState['entity_states'][$target_entity_type]['count'];
        $delta = 0;

        $row = static::$fieldTableTemplates;
        $row['bundle'] = $bundle_type;
        $row['entity_id'] = $entity_id;
        $row['revision_id'] = $entity_id;

        for ($instance_index = $num_of_instances; $instance_index > 0; $instance_index--) {
          $row['delta'] = $delta;
          $row['target_id'] = $end_target_entity_id - $instance_index;

          if ($reference_type === 'entity_reference_revisions') {
            $row['target_revision_id'] = $row['target_id'];
          }

          $csv_row = array_values($row);
          fputcsv($this->cacheCsvFileHandlers[$entity_type][$table_name], $csv_row);
          fputcsv($this->cacheCsvFileHandlers[$entity_type][$rev_table_name], $csv_row);

          $delta++;
        }

        unset($this->entityTypeReferenceNestingStack[$unique_bundle_key]);
      }
    }
  }

  /**
   * Get list of bundle types with number of instances for entity type.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return array
   *   The list of bundle types with number of instances.
   */
  protected function getEntityBundleInstances($entity_type) {
    if (isset($this->cacheBundleInstances[$entity_type])) {
      return $this->cacheBundleInstances[$entity_type];
    }

    foreach ($this->config[$entity_type]['_bundles'] as $bundle_type => $bundle) {
      $this->cacheBundleInstances[$entity_type][$bundle_type] = $bundle['instances'];
    }

    return $this->cacheBundleInstances[$entity_type];
  }

  /**
   * Generic function for weighted random.
   *
   * TODO: Speed up. Make binary tree range search. It should be: O(log(n))
   * And be sure to prepare data before!
   *
   * @param array $list
   *   List with keys and their weights.
   *
   * @return string
   *   Returns one of keys from list.
   */
  protected function getRandomWeighted(array $list) {
    $rand = mt_rand(1, (int) array_sum($list));

    foreach ($list as $key => $value) {
      $rand -= $value;
      if ($rand <= 0) {
        return $key;
      }
    }
  }

  /**
   * Import generated CSV files into database.
   */
  public function importCsvFiles() {
    // Some performance boost flags.
    $this->database->query("SET unique_checks = 0")->execute();
    $this->database->query("SET foreign_key_checks = 0")->execute();
    $this->database->query("SET sql_log_bin=0")->execute();

    $list_of_tables = [];

    // Prepare tables for import.
    foreach (glob($this->outputDirectory . '/*.csv') as $file) {
      $table_name = basename($file, ".csv");

      $list_of_tables[] = $table_name;
      $this->database->truncate($table_name)->execute();
    }

    $this->database->query("SET autocommit = 0")->execute();
    foreach ($list_of_tables as $table_name) {
      $csv_file_name = $this->outputDirectory . '/' . $table_name . '.csv';

      $import_query = "LOAD DATA INFILE '{$csv_file_name}'" . PHP_EOL .
        "IGNORE INTO TABLE `{$table_name}`" . PHP_EOL .
        "FIELDS TERMINATED BY ','" . PHP_EOL .
        "ENCLOSED BY '\"'" . PHP_EOL .
        "LINES TERMINATED BY '\n'" . PHP_EOL .
        "IGNORE 1 ROWS;";

      $this->database->query($import_query)->execute();
    }
    $this->database->query("commit")->execute();
  }

}
