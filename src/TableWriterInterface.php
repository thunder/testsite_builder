<?php

namespace Drupal\testsite_builder;

/**
 * The interface for table writer used for content creator.
 *
 * @package Drupal\testsite_builder
 */
interface TableWriterInterface {

  /**
   * Create table for writer.
   *
   * @param string $table_name
   *   The table name.
   */
  public function create($table_name);

  /**
   * Write single row to table.
   *
   * @param string $table_name
   *   The table name.
   * @param array $row
   *   The row to write in table.
   */
  public function write($table_name, array $row);

  /**
   * Outputs all table data to related storage.
   */
  public function outputAll();

  /**
   * Delete all tables.
   */
  public function cleanAll();

}
