<?php

namespace Drupal\testsite_builder;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

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
   * Database connection.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Base entity tables plugin manager.
   *
   * @var \Drupal\testsite_builder\BaseEntityTablesPluginManager
   */
  protected $baseEntityTablesPluginManager;

  /**
   * Configuration for ContentCreator.
   *
   * This is main source of information to generate CSV files.
   *
   * @var array
   */
  protected $config = [];

  /**
   * List of sample data types with generated data.
   *
   * @var array
   */
  protected $sampleDataTypes = [];

  /**
   * CSV Table writer.
   *
   * @var \Drupal\testsite_builder\CsvTableWriter
   */
  protected $csvTableWriter = NULL;

  /**
   * Cache of number of bundle instances for entity type.
   *
   * @var array
   */
  protected $cacheBundleInstances = [];

  /**
   * Cache of bundle instances for entity type used by reference field.
   *
   * @var array
   */
  protected $cacheBundleInstancesForField = [];

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
   * Keeps row template for base entity tables.
   *
   * @var array
   */
  protected $cacheBaseTableRowTemplates = [];

  /**
   * Keeps instances of base entity tables plugins.
   *
   * @var array
   */
  protected $cacheBaseEntityTablesPlugin = [];

  /**
   * Keeps track of number of created entities per type.
   *
   * @var array
   */
  protected $entityCounts = [];

  /**
   * Keeps track of created IDs per bundle.
   *
   * @var array
   */
  protected $entityBundleIDs = [];

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
   * The content creator storage service.
   *
   * @var \Drupal\testsite_builder\ContentCreatorStorage
   */
  protected $storage;

  /**
   * Constructs a new ContentCreator object.
   *
   * @param \Drupal\testsite_builder\ContentCreatorStorage $storage
   *   The content creator storage service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\testsite_builder\BaseEntityTablesPluginManager $base_entity_tables_plugin_manager
   *   The base entity tables plugin manager service.
   */
  public function __construct(ContentCreatorStorage $storage, Connection $database, BaseEntityTablesPluginManager $base_entity_tables_plugin_manager) {
    $this->storage = $storage;
    $this->database = $database;
    $this->baseEntityTablesPluginManager = $base_entity_tables_plugin_manager;
  }

  /**
   * Init global state.
   */
  protected function init() {
    $this->config = $this->storage->getConfig();
    $this->sampleDataTypes = $this->storage->getSampleData();

    $csv_writer = new CsvTableWriter();
    $this->csvTableWriter = $csv_writer;

    // Make deterministic random seed.
    srand(0);
  }

  /**
   * Entry function to create CSV files with data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createCsvFiles() {
    $this->init();

    foreach ($this->baseEntityTypes as $entity_type) {
      foreach ($this->config[$entity_type]['_bundles'] as $bundle_type => $bundle) {
        $this->createBundle($entity_type, $bundle_type, $bundle['instances']);
      }
    }

    // Output table writer data to files.
    $this->csvTableWriter->outputAll();
  }

  /**
   * Get base entity tables plugin.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return \Drupal\testsite_builder\BaseEntityTablesInterface
   *   Returns instance of entity base table plugin for provided entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getBaseEntityTablesPlugin($entity_type) {
    if (isset($this->cacheBaseEntityTablesPlugin[$entity_type])) {
      return $this->cacheBaseEntityTablesPlugin[$entity_type];
    }

    if ($this->baseEntityTablesPluginManager->hasDefinition($entity_type)) {
      $plugin = $this->baseEntityTablesPluginManager->createInstance($entity_type, $this->config[$entity_type]);
      $this->cacheBaseEntityTablesPlugin[$entity_type] = $plugin;

      return $this->cacheBaseEntityTablesPlugin[$entity_type];
    }

    $plugin = $this->baseEntityTablesPluginManager->createInstance('generic', $this->config[$entity_type]);
    $this->cacheBaseEntityTablesPlugin[$entity_type] = $plugin;

    return $this->cacheBaseEntityTablesPlugin[$entity_type];
  }

  /**
   * Return row templates for base tables.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return array
   *   Returns row templates for base tables.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getBaseTableTemplates($entity_type) {
    if (isset($this->cacheBaseTableRowTemplates[$entity_type])) {
      return $this->cacheBaseTableRowTemplates[$entity_type];
    }

    $base_entity_tables_plugin = $this->getBaseEntityTablesPlugin($entity_type);
    $this->cacheBaseTableRowTemplates[$entity_type] = $base_entity_tables_plugin->getBaseTableTemplates();

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
   * @param int $parent_id
   *   The parent entity id.
   * @param string $parent_type
   *   The parent entity type.
   * @param string $parent_field_name
   *   The parent field name with references to new created entities.
   *
   * @return array
   *   Returns IDs of created entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createBundle($entity_type, $bundle_type, $num_of_instances, $parent_id = NULL, $parent_type = NULL, $parent_field_name = NULL) {
    if (!isset($this->entityCounts[$entity_type])) {
      $this->entityCounts[$entity_type]['_total_count'] = 1;
    }

    if (!isset($this->entityCounts[$entity_type][$bundle_type])) {
      $this->entityCounts[$entity_type][$bundle_type] = 0;
    }
    $this->entityCounts[$entity_type][$bundle_type] += $num_of_instances;

    $unique_bundle_key = $entity_type . '_' . $bundle_type;
    if (isset($this->entityTypeReferenceNestingStack[$unique_bundle_key])) {
      return [];
    }

    $entity_type_state = [
      'entity_type' => $entity_type,
      'bundle_type' => $bundle_type,
      'parent_id' => $parent_id,
      'parent_type' => $parent_type,
      'parent_field_name' => $parent_field_name,
    ];

    $this->entityTypeReferenceNestingStack[$unique_bundle_key] = $entity_type_state;

    $uuid_part_entity_type = array_search($entity_type, array_keys($this->entityCounts));
    $uuid_part_bundle_type = array_search($bundle_type, array_keys($this->entityCounts[$entity_type]));
    $total_entity_type_count = $this->entityCounts[$entity_type]['_total_count'];

    $column_mapping = $this->config[$entity_type]['_entity_definition_keys'];

    // Prepare row arrays.
    $row_templates = $this->getBaseTableTemplates($entity_type);
    $this->getBaseEntityTablesPlugin($entity_type)
      ->alterRowTemplate($row_templates, $entity_type_state);
    $variable_row_data = [
      'bundle' => $bundle_type,
    ];

    // Fill data into CSV file.
    for ($instance_index = 0; $instance_index < $num_of_instances; $instance_index++) {
      $variable_row_data['id'] = $total_entity_type_count;
      $variable_row_data['revision'] = $total_entity_type_count;
      $variable_row_data['uuid'] = sprintf("%'.08d-%'.04d-0000-0000-%'.012d", $uuid_part_entity_type, $uuid_part_bundle_type, $total_entity_type_count);
      $variable_row_data['label'] = $bundle_type . ' ' . $total_entity_type_count;

      foreach ($row_templates as $table_name => $row_template) {
        $row = $row_template;
        foreach ($variable_row_data as $column => $value) {
          if (isset($column_mapping[$column]) && array_key_exists($column_mapping[$column], $row)) {
            $row[$column_mapping[$column]] = $value;

            continue;
          }
        }

        $this->csvTableWriter->write($table_name, array_values($row));
      }

      $this->entityBundleIDs[$entity_type][$bundle_type][] = $total_entity_type_count;
      $total_entity_type_count++;
    }

    $this->entityCounts[$entity_type]['_total_count'] = $total_entity_type_count;

    $this->createBundleFields($entity_type, $bundle_type, $total_entity_type_count - $num_of_instances, $total_entity_type_count);
    $this->createEntityReferenceFields($entity_type, $bundle_type, $total_entity_type_count - $num_of_instances, $total_entity_type_count);

    unset($this->entityTypeReferenceNestingStack[$unique_bundle_key]);

    return range($total_entity_type_count - $num_of_instances + 1, $total_entity_type_count);
  }

  /**
   * Creates entity instances for referenced fields or reuse existing ones.
   *
   * Entities will be reused only if creation of new instance would exceed real
   * number of instances.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   * @param int $num_of_instances
   *   The number of instances for bundle.
   * @param int $parent_id
   *   The parent entity id.
   * @param string $parent_type
   *   The parent entity type.
   * @param string $parent_field_name
   *   The parent field name with references to new created entities.
   *
   * @return array
   *   Returns IDs of created entities or existing ones.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createBundleForReferenceField($entity_type, $bundle_type, $num_of_instances, $parent_id, $parent_type, $parent_field_name) {
    if ($entity_type !== 'paragraph' && isset($this->entityCounts[$entity_type][$bundle_type]) && $this->entityCounts[$entity_type][$bundle_type] >= $this->config[$entity_type]['_bundles'][$bundle_type]['instances']) {
      $instance_ids = array_rand($this->entityBundleIDs[$entity_type][$bundle_type], $num_of_instances);

      return is_array($instance_ids) ? $instance_ids : [$instance_ids];
    }

    return $this->createBundle($entity_type, $bundle_type, $num_of_instances, $parent_id, $parent_type, $parent_field_name);
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

      $field_definitions[] = [
        'type' => $type,
        'table_name' => $field['_table']['name'],
        'rev_table_name' => $field['_rev_table']['name'],
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

        $values = $this->getSampleData($type);
        foreach ($values as $key => $value) {
          $row[$type . '_' . $key] = $value;
        }

        $csv_row = array_values($row);
        $this->csvTableWriter->write($table_name, $csv_row);
        $this->csvTableWriter->write($rev_table_name, $csv_row);
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
      if (!in_array($target_entity_type, ['media', 'paragraph', 'taxonomy_term'])) {
        continue;
      }

      $field_definitions[] = [
        'type' => $field['field_type'],
        'field_name' => $field['field_name'],
        'reference_type' => $field['_bundle_info']['reference'],
        'target_type' => $target_entity_type,
        'histogram' => $field['_bundle_info']['histogram'],
        'table_name' => $field['_table']['name'],
        'rev_table_name' => $field['_rev_table']['name'],
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
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createEntityReferenceFields($entity_type, $bundle_type, $start_entity_id, $end_entity_id) {
    $referenced_field_definitions = $this->getBundleReferencedFieldDefinitions($entity_type, $bundle_type);
    foreach ($referenced_field_definitions as $referenced_field_definition) {
      $histogram = $referenced_field_definition['histogram'];
      if (empty($histogram)) {
        continue;
      }

      $target_entity_type = $referenced_field_definition['target_type'];
      $reference_type = $referenced_field_definition['reference_type'];
      $table_name = $referenced_field_definition['table_name'];
      $rev_table_name = $referenced_field_definition['rev_table_name'];
      $field_name = $referenced_field_definition['field_name'];
      for ($entity_id = $start_entity_id; $entity_id < $end_entity_id; $entity_id++) {
        $bundle_instances_for_field = $this->getEntityBundleInstancesForField($entity_type, $bundle_type, $field_name);
        if (empty($bundle_instances_for_field)) {
          continue;
        }

        $target_entity_bundles = [];
        $num_of_instances = $this->getRandomWeighted($histogram);
        for ($instance_index = $num_of_instances; $instance_index >= 0; $instance_index--) {
          $target_entity_bundles[] = $this->getRandomWeighted($bundle_instances_for_field);
        }

        $delta = 0;
        foreach ($target_entity_bundles as $target_entity_bundle) {
          // Block creation early for deep nested references.
          if (isset($this->entityTypeReferenceNestingStack[$target_entity_type . '_' . $target_entity_bundle])) {
            continue;
          }

          $row = static::$fieldTableTemplates;
          $row['bundle'] = $bundle_type;
          $row['entity_id'] = $entity_id;
          $row['revision_id'] = $entity_id;
          $row['delta'] = $delta;
          $row['target_id'] = $this->createBundleForReferenceField($target_entity_type, $target_entity_bundle, 1, $entity_id, $entity_type, $field_name)[0];

          if ($reference_type === 'entity_reference_revisions') {
            $row['target_revision_id'] = $row['target_id'];
          }

          $csv_row = array_values($row);
          $this->csvTableWriter->write($table_name, $csv_row);
          $this->csvTableWriter->write($rev_table_name, $csv_row);

          $delta++;
        }
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
   * Get list of bundle types with number of instances for field.
   *
   * TODO: Add support optional fields where 0 entities are referenced.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The list of bundle types with number of instances.
   */
  protected function getEntityBundleInstancesForField($entity_type, $bundle_type, $field_name) {
    if (isset($this->cacheBundleInstancesForField[$entity_type][$bundle_type][$field_name])) {
      return $this->cacheBundleInstancesForField[$entity_type][$bundle_type][$field_name];
    }

    $field_target_bundle_info = $this->config[$entity_type]['_bundles'][$bundle_type]['_fields'][$field_name]['_bundle_info'];
    $this->cacheBundleInstancesForField[$entity_type][$bundle_type][$field_name] = array_filter($this->getEntityBundleInstances($field_target_bundle_info['target_type']), function ($target_bundle_type) use ($field_target_bundle_info) {
      return empty($field_target_bundle_info['target_bundles']) || isset($field_target_bundle_info['target_bundles'][$target_bundle_type]);
    }, ARRAY_FILTER_USE_KEY);

    return $this->cacheBundleInstancesForField[$entity_type][$bundle_type][$field_name];
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
    $rand = rand(1, (int) array_sum($list));

    foreach ($list as $key => $value) {
      $rand -= $value;
      if ($rand <= 0) {
        return $key;
      }
    }
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
   *   Returns sample data.
   */
  protected function getSampleData($type, $random = TRUE) {
    if (!isset($this->sampleDataTypes[$type])) {
      return [];
    }

    if (!$random) {
      return $this->sampleDataTypes[$type][0];
    }

    return $this->sampleDataTypes[$type][rand(0, count($this->sampleDataTypes[$type]) - 1)];
  }

  /**
   * Import generated CSV files into database.
   */
  public function importCsvFiles() {
    // We are trying to be nice. (fe. 8 cores -> 6 forks).
    $number_of_forks = ceil($this->getNumberOfCores() / 1.5);

    $list_of_tables = [];
    $table_pool_size = [];
    for ($fork_index = 0; $fork_index < $number_of_forks; $fork_index++) {
      $list_of_tables[$fork_index] = [];
      $table_pool_size[$fork_index] = 0;
    }

    // Get directory where table content is persisted in CSV files.
    $output_directory = $this->getOutputDirectory();

    // Prepare tables for import.
    $list_of_files = glob($output_directory . '/*.csv');
    // Add randomization to avoid big tables at end.
    shuffle($list_of_files);
    foreach ($list_of_files as $file) {
      $table_name = basename($file, ".csv");

      $csv_file_name = $output_directory . '/' . $table_name . '.csv';
      $file_size = filesize($csv_file_name);

      // Distribute tables based on file size.
      // TODO: Check if distribution can be improved, filesize works for now.
      $pool_index = array_keys($table_pool_size, min($table_pool_size))[0];
      $list_of_tables[$pool_index][] = $table_name;
      $table_pool_size[$pool_index] += $file_size;

      $this->database->truncate($table_name)->execute();
    }

    // Best performance boost for table imports is made by parallel imports.
    $children_pids = [];
    for ($fork_index = 0; $fork_index < $number_of_forks; $fork_index++) {
      switch ($pid = pcntl_fork()) {
        case -1:
          break;

        case 0:
          $db_conn = $this->getPdoConnection();

          // Some performance boost flags.
          $db_conn->query("SET unique_checks=0")->execute();
          $db_conn->query("SET foreign_key_checks=0")->execute();
          $db_conn->query("SET sql_log_bin=0")->execute();

          // Import tables distributed to this fork.
          foreach ($list_of_tables[$fork_index] as $table_name) {
            $csv_file_name = $output_directory . '/' . $table_name . '.csv';
            $db_conn->query("SET autocommit = 0")->execute();
            $import_query = "LOAD DATA INFILE '{$csv_file_name}'" . PHP_EOL .
              "IGNORE INTO TABLE `{$table_name}`" . PHP_EOL .
              "FIELDS TERMINATED BY ','" . PHP_EOL .
              "ENCLOSED BY '\"'" . PHP_EOL .
              "LINES TERMINATED BY '\n'" . PHP_EOL .
              "IGNORE 1 ROWS;";

            $db_conn->query($import_query)->execute();
            $db_conn->query("commit")->execute();
          }

          // We have to exit from child process here!
          // TODO: "return" statement would lead to multiple console outputs.
          exit(0);

        default:
          // For parent fork, we are collecting children.
          $children_pids[$fork_index] = $pid;

          break;
      }
    }

    while (!empty($children_pids)) {
      sleep(5);

      foreach ($children_pids as $fork_index => $pid) {
        $status = NULL;
        $wait_result = pcntl_waitpid($pid, $status, WNOHANG);

        if ($wait_result == -1 || $wait_result > 0) {
          unset($children_pids[$fork_index]);
        }
      }
    }
  }

  /**
   * Get number of cores on machine.
   *
   * @return int
   *   Returns number of cores.
   */
  protected function getNumberOfCores() {
    if (is_file('/proc/cpuinfo')) {
      $cpu_info = file_get_contents('/proc/cpuinfo');
      preg_match_all('/^processor/m', $cpu_info, $matches);

      return count($matches[0]);
    }

    $process = @popen('sysctl -a', 'rb');
    if ($process) {
      $output = stream_get_contents($process);
      preg_match('/hw.ncpu: (\d+)/', $output, $matches);
      pclose($process);

      if ($matches) {
        return intval($matches[1][0]);
      }
    }

    return 1;
  }

  /**
   * Get PDO connection.
   *
   * It's required for forked mysql execution.
   *
   * @return \PDO
   *   Returns PDO database connection.
   */
  protected function getPdoConnection() {
    $connection_info = Database::getConnectionInfo('default')['default'];

    $namespace = (isset($connection_info['namespace'])) ? $connection_info['namespace'] : 'Drupal\\Core\\Database\\Driver\\' . $connection_info['driver'];
    $driver_class = $namespace . '\\Connection';

    return $driver_class::open($connection_info);
  }

  /**
   * Get created temporally output directory.
   *
   * @return string
   *   Returns the output directory.
   */
  public function getOutputDirectory() {
    return $this->csvTableWriter->getOutputDirectory();
  }

  /**
   * Remove created temporally output folder.
   */
  public function cleanUp() {
    $this->csvTableWriter->cleanAll();
  }

}
