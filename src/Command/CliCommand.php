<?php
namespace Civi\Cv\Command;

// **********************
// ** WORK IN PROGRESS **
// **********************

use Civi\Cv\Application;
use Civi\Cv\Util\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class CliCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('cli')
      ->setDescription('Load interactive command line');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    $cv = new Application();
    $sh = new \Psy\Shell();
    $sh->addCommands($cv->createCommands());
    // When I try making a new matcher, it doesn't seem to get called.
    //$sh->addTabCompletionMatchers(array(
    //  new ApiMatcher(),
    //));
    $sh->run();
  }

}
