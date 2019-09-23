<?php

namespace Drupal\testsite_builder\Command;

use Drupal\testsite_builder\ConfigCreator;
use Drupal\testsite_builder\ContentCreator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
// phpcs:disable
use Drupal\Console\Annotations\DrupalCommand;
// phpcs:enable

/**
 * Class CreateConfigCommand.
 *
 * @DrupalCommand (
 *     extension="testsite_builder",
 *     extensionType="module"
 * )
 */
class CreateConfigCommand extends ContainerAwareCommand {

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
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('testsite-builder:create-config')
      ->addArgument('file', InputArgument::REQUIRED, $this->trans('commands.testsite_builder.create-config.arguments.file'))
      ->addOption('create-content', NULL, NULL, 'commands.testsite_builder.create-config.options.create-content')
      ->addOption('keep-content-files', NULL, NULL, 'commands.testsite_builder.create-config.options.keep-content-files')
      ->setDescription($this->trans('commands.testsite_builder.create-config.description'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = $this->getIo();

    $keep_content_files = $input->getOption('keep-content-files');
    $create_content = $input->getOption('create-content');

    // Option keep-content-files is not valid without create-content.
    if ($keep_content_files && !$create_content) {
      $io->error($this->trans('commands.testsite_builder.create-config.messages.content_invalid_keep_files'));

      return;
    }

    $file = $input->getArgument('file');
    $this->configCreator->setReportData($file);

    $io->newLine();
    $auto_yes = $input->hasOption('yes') ? $input->getOption('yes') : FALSE;
    if (!$auto_yes && !$io->confirm($this->trans('commands.testsite_builder.create-config.messages.confirm'))) {
      $io->comment($this->trans('commands.common.questions.canceled'));
      return;
    }

    $io->comment($this->trans('commands.testsite_builder.create-config.messages.config_cleanup'));
    $this->beforeAction();
    $this->configCreator->cleanup();
    $this->afterAction();

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-config.messages.config_create'));
    $this->beforeAction();
    $this->configCreator->create();
    $this->afterAction();

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-config.messages.config_create_fix_missing_config'));
    $this->beforeAction();
    $imported_configurations = $this->configCreator->fixMissingConfiguration();
    $this->afterAction();

    // List imported missing configurations.
    foreach ($imported_configurations as $dependent_config => $missing_configs) {
      foreach ($missing_configs as $missing_config) {
        $io->warningLite(
          sprintf(
            $this->trans('commands.testsite_builder.create-config.messages.config_create_missing_config'),
             $missing_config,
             $dependent_config
          )
        );
      }
    }

    $io->newLine();
    $io->comment($this->trans('commands.cache.rebuild.messages.rebuild'));
    $command = $this->getApplication()->find('cache:rebuild');
    $this->beforeAction();
    $command->run(new ArrayInput([]), new NullOutput());
    $this->afterAction();

    if (!$create_content) {
      $io->newLine();
      $io->success($this->trans('commands.testsite_builder.create-config.messages.config_success'));

      return;
    }

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-config.messages.content_create'));
    $this->beforeAction();
    $this->contentCreator->createCsvFiles();
    $this->afterAction();

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-config.messages.content_import'));
    $this->beforeAction();
    $this->contentCreator->importCsvFiles($keep_content_files);
    $this->afterAction();

    $io->newLine();
    if ($keep_content_files) {
      $io->comment($this->trans('commands.testsite_builder.create-config.messages.content_output_directory'));
      $io->comment($this->contentCreator->getOutputDirectory());
    }
    else {
      $io->comment($this->trans('commands.testsite_builder.create-config.messages.content_cleanup'));
      $this->beforeAction();
      $this->contentCreator->cleanUp();
      $this->afterAction();
    }

    $io->newLine();
    $io->success($this->trans('commands.testsite_builder.create-config.messages.success'));
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
    $this->getIo()->info($this->trans('commands.testsite_builder.create-config.messages.report_time') . $execution_time . " [s]");
  }

}
