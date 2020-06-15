<?php

namespace Drupal\testsite_builder\Commands;

use Drupal\testsite_builder\ConfigCreator;
use Drupal\testsite_builder\ContentCreator;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Class CreateConfigCommand.
 */
class CreateConfigCommand extends DrushCommands {

  /**
   * The reporter instance.
   *
   * @var \Drupal\testsite_builder\ConfigCreator
   */
  protected $configCreator;

  /**
   * The content creator service.
   *
   * @var \Drupal\testsite_builder\ContentCreator
   */
  protected $contentCreator;

  /**
   * The moment when action is started.
   *
   * This used to measure time needed to finish different actions in process.
   *
   * @var int
   */
  protected $startActionTime;

  /**
   * Constructs a new CreateConfigCommand object.
   *
   * @param \Drupal\testsite_builder\ConfigCreator $config_creator
   *   The config creator service.
   * @param \Drupal\testsite_builder\ContentCreator $content_creator
   *   The content creator service.
   */
  public function __construct(ConfigCreator $config_creator, ContentCreator $content_creator) {
    $this->configCreator = $config_creator;
    $this->contentCreator = $content_creator;
  }

  /**
   * Create config from a report file.
   *
   * @param string $file The report file the config should created of.
   *
   * @option create-content Create content after config creation.
   * @option keep-content-files Keep files generated by content creator. This option can be used only in combination with --create-content.
   *
   * @argument file The report file the config should created of.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @command testsite-builder:create-config
   */
  public function createConfig($file, array $options = ['create-content' => FALSE, 'keep-content-files' => FALSE]) {

    // Option keep-content-files is not valid without create-content.
    if ($options['keep-content-files'] && !$options['create-content']) {
      $this->io()->error('You are not able to use --keep-content-files without --create-content option.');
      return;
    }

    if (!file_exists($file)) {
      $this->io()->comment('File not found.');
      return;
    }

    $this->configCreator->setReportData($file);

    $this->io()->newLine();
    $auto_yes = $this->input()->hasOption('yes') ? $this->input()->getOption('yes') : FALSE;
    if (!$auto_yes && !$this->io()->confirm('Are you sure you want to delete all existing content and the configuration of most of the content entity types?')) {
      $this->io()->comment('Canceled.');
      return;
    }

    $this->io()->comment('Cleanup - Deleting existing bundles, fields...');
    $this->beforeAction();
    $this->configCreator->cleanup();
    $this->afterAction();

    $this->io()->newLine();
    $this->io()->comment('Preparation - Creating new bundles and fields...');
    $this->beforeAction();
    $this->configCreator->create();
    $this->afterAction();

    $this->io()->newLine();
    $this->io()->comment('Mending - Fixing missing configuration dependencies...');
    $this->beforeAction();
    $imported_configurations = $this->configCreator->fixMissingConfiguration();
    $this->afterAction();

    // List imported missing configurations.
    foreach ($imported_configurations as $dependent_config => $missing_configs) {
      foreach ($missing_configs as $missing_config) {
        $this->io()->warning(
          sprintf(
            'Missing config <fg=red>%s</> required by: <comment>%s</comment>.',
             $missing_config,
             $dependent_config
          )
        );
      }
    }

    $this->io()->newLine();
    $this->io()->comment('Mending - Importing configurations from templates...');
    $this->beforeAction();
    $imported_templates = $this->configCreator->importTemplateConfigurations();
    $this->afterAction();

    // List imported templates.
    foreach ($imported_templates as $template_file) {
      $this->io()->warning(
        sprintf(
          'Imported template config: <comment>%s</comment>.',
          $template_file
        )
      );
    }

    $this->io()->newLine();
    $this->io()->comment('Cache rebuild.');
    $application = Drush::getApplication();
    $command = $application->find('cache:rebuild');
    $this->beforeAction();
    $command->run(new ArrayInput([]), new NullOutput());
    $this->afterAction();

    if (!$options['create-content']) {
      $this->io()->newLine();
      $this->io()->success('Configuration successfully created.');

      return;
    }

    $this->io()->newLine();
    $this->io()->comment('Creating content - Create CSV files with content for previously created configuration...');
    $this->beforeAction();
    $this->contentCreator->createCsvFiles();
    $this->afterAction();

    $this->io()->newLine();
    $this->io()->comment('Importing content - Importing CSV files with content into database...');
    $this->beforeAction();
    $this->contentCreator->importCsvFiles($options['keep-content-files']);
    $this->afterAction();

    $this->io()->newLine();
    if ($options['keep-content-files']) {
      $this->io()->comment('Created configuration JSON for creation of content and CSV files with content are stored in: ');
      $this->io()->comment($this->contentCreator->getOutputDirectory());
    }
    else {
      $this->io()->comment('Cleanup - Deleting created CSV files with content...');
      $this->beforeAction();
      $this->contentCreator->cleanUp();
      $this->afterAction();
    }

    $this->io()->newLine();
    $this->io()->success('Configuration with content successfully created.');
  }

  /**
   * Start tracking time needed to execute action.
   */
  protected function beforeAction() {
    $this->startActionTime = microtime(TRUE);
  }

  /**
   * End tracking time needed to execute action and report it.
   */
  protected function afterAction() {
    $end_time = microtime(TRUE);

    $execution_time = ($end_time - $this->startActionTime);
    $this->io()->text('Done: ' . $execution_time . " [s]");
  }

}