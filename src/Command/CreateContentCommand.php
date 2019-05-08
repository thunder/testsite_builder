<?php

namespace Drupal\testsite_builder\Command;

use Drupal\testsite_builder\ContentCreator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
// phpcs:disable
use Drupal\Console\Annotations\DrupalCommand;
// phpcs:enable

/**
 * Class CreateContentCommand.
 *
 * @DrupalCommand (
 *     extension="testsite_builder",
 *     extensionType="module"
 * )
 */
class CreateContentCommand extends ContainerAwareCommand {

  /**
   * The reporter instance.
   *
   * @var \Drupal\testsite_builder\ContentCreator
   */
  protected $contentCreator;

  /**
   * Constructs a new ContentCreatorCommand object.
   */
  public function __construct(ContentCreator $contentCreator) {
    $this->contentCreator = $contentCreator;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('testsite-builder:create-content')
      ->addArgument('config', InputArgument::REQUIRED, $this->trans('commands.testsite_builder.create-content.arguments.config'))
      ->addArgument('sampled-data', InputArgument::REQUIRED, $this->trans('commands.testsite_builder.create-content.arguments.sampled-data'))
      ->addArgument('output-directory', InputArgument::REQUIRED, $this->trans('commands.testsite_builder.create-content.arguments.output-directory'))
      ->setDescription($this->trans('commands.testsite_builder.create-content.description'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $config_file = $input->getArgument('config');
    $sampled_data_file = $input->getArgument('sampled-data');
    $output_dir = $input->getArgument('output-directory');

    $this->contentCreator->setConfig(json_decode(file_get_contents($config_file), TRUE));
    $this->contentCreator->setSampledData(json_decode(file_get_contents($sampled_data_file), TRUE));
    $this->contentCreator->setOutputDirectory($output_dir);

    $io = $this->getIo();

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-content.messages.create_content'));
    $start_time = microtime(TRUE);
    $this->contentCreator->createCsvFiles();
    $end_time = microtime(TRUE);
    $execution_time = ($end_time - $start_time);
    $io->info("Done: " . $execution_time . " [s]");

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-content.messages.import_content'));
    $start_time = microtime(TRUE);
    $this->contentCreator->importCsvFiles();
    $end_time = microtime(TRUE);
    $execution_time = ($end_time - $start_time);
    $io->info("Done: " . $execution_time . " [s]");

    $io->newLine();
    $io->success($this->trans('commands.testsite_builder.create-content.messages.success'));
  }

}
