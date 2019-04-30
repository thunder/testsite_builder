<?php

namespace Drupal\testsite_builder\Command;

use Drupal\testsite_builder\ConfigCreator;
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
   * Constructs a new CreateConfigCommand object.
   */
  public function __construct(ConfigCreator $configCreator) {
    $this->configCreator = $configCreator;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('testsite-builder:create-config')
      ->addArgument('file', InputArgument::REQUIRED, $this->trans('commands.testsite_builder.create-config.arguments.file'))
      ->setDescription($this->trans('commands.testsite_builder.create-config.description'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $file = $input->getArgument('file');

    $this->configCreator->setReportData($file);

    $io = $this->getIo();
    $io->newLine();
    if (!$io->confirm($this->trans('commands.testsite_builder.create-config.messages.confirm'))) {
      $io->comment($this->trans('commands.common.questions.canceled'));
      return;
    }

    $io->comment($this->trans('commands.testsite_builder.create-config.messages.cleanup'));
    $this->configCreator->cleanup();

    $io->newLine();
    $io->comment($this->trans('commands.testsite_builder.create-config.messages.create_config'));
    $this->configCreator->create();

    $io->newLine();
    $io->comment($this->trans('commands.cache.rebuild.messages.rebuild'));
    $command = $this->getApplication()->find('cache:rebuild');
    $command->run(new ArrayInput([]), new NullOutput());

    $io->newLine();
    $io->success($this->trans('commands.testsite_builder.create-config.messages.success'));
  }

}
