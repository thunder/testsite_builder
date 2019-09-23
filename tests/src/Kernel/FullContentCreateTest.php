<?php

namespace Drupal\Tests\testsite_builder\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests full content create based on small site sample.
 *
 * @group testsite_builder
 */
class FullContentCreateTest extends KernelTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'testsite_builder',
  ];

  /**
   * Test final generated tables of Content Creator.
   */
  public function testContentCreate() {
    $content_creator_storage = $this->container->get('testsite_builder.content_creator_storage');
    $fixtures_path = realpath(dirname(__FILE__) . '/../../fixtures/full_content_create');

    $config = json_decode(file_get_contents("{$fixtures_path}/config.json"), TRUE);
    $content_creator_storage->addConfig([], $config);

    $data_types = json_decode(file_get_contents("{$fixtures_path}/data_types.json"), TRUE);
    foreach ($data_types as $type => $sample_data) {
      $content_creator_storage->addSampleData($type, $sample_data);
    }

    /** @var \Drupal\testsite_builder\ContentCreator $content_creator */
    $content_creator = $this->container->get('testsite_builder.content_creator');
    $content_creator->createCsvFiles();

    $all_tables = [];
    $generated_content_files = glob("{$content_creator->getOutputDirectory()}/*.csv");
    foreach ($generated_content_files as $full_file_path) {
      $table_name = basename($full_file_path, ".csv");
      $all_tables[$table_name] = sha1_file($full_file_path);
    }

    // Get list of expected table names with sha1 of generated content for them.
    $expected_tables = json_decode(file_get_contents("{$fixtures_path}/expected_file_content.json"), TRUE);

    // Test that there is not difference in number of created tables.
    $missing_expected_tables = array_diff_key($expected_tables, $all_tables);
    $this->assertEqual([], $missing_expected_tables, "All expected tables should be generated.");

    $additional_tables = array_diff_key($all_tables, $expected_tables);
    $this->assertEqual([], $additional_tables, "There should not be additional tables generated.");

    // Validate that content of tables are same.
    foreach ($all_tables as $table_name => $table_content_hash) {
      $this->assertEqual($expected_tables[$table_name], $table_content_hash, "Content is different for table: {$table_name}.");
    }

    // Delete all temp files after test.
    $content_creator->cleanUp();
  }

}
