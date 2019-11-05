<?php

namespace Drupal\testsite_builder;

/**
 * Table writer with output to CSV files.
 *
 * @package Drupal\testsite_builder
 */
class CsvTableWriter implements TableWriterInterface {

  /**
   * Keeps open CSV handlers.
   *
   * @var array
   */
  protected $csvHandlers = [];

  /**
   * Output directory for CSV files.
   *
   * @var string
   */
  protected $outputDirectory = NULL;

  /**
   * Creates CSV Table writer with output to temp folder.
   */
  public function __construct() {
    $this->outputDirectory = sys_get_temp_dir() . '/' . uniqid('csv_table_writer_', TRUE);
    mkdir($this->outputDirectory, 0777, TRUE);
  }

  /**
   * Gets directory path where CSV files will be saved.
   *
   * @return string
   *   Returns output directory path.
   */
  public function getOutputDirectory() {
    return $this->outputDirectory;
  }

  /**
   * {@inheritdoc}
   */
  public function create($table_name) {
    $this->csvHandlers[$table_name] = fopen($this->outputDirectory . '/' . $table_name . '.csv', 'w');
  }

  /**
   * {@inheritdoc}
   */
  public function write($table_name, array $row) {
    if (empty($this->csvHandlers[$table_name])) {
      $this->create($table_name);
    }

    fputcsv($this->csvHandlers[$table_name], $row);
  }

  /**
   * {@inheritdoc}
   */
  public function outputAll() {
    foreach ($this->csvHandlers as $csv_file) {
      fclose($csv_file);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanAll() {
    // Remove all files and output directory it self.
    array_map('unlink', glob("{$this->outputDirectory}/*"));
    rmdir($this->outputDirectory);
  }

}
