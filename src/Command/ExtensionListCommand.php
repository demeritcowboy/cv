<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ArrayUtil;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionListCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:list')
      ->setAliases(array())
      ->setDescription('List extensions')
      ->addOption('local', 'L', InputOption::VALUE_NONE, 'Show local extensions')
      ->addOption('remote', 'R', InputOption::VALUE_NONE, 'Show remote extensions')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the list of extensions')
      ->addOption('columns', NULL, InputOption::VALUE_REQUIRED, 'List of columns to display (comma separated)', 'location,key,name,version,status')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (table,' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat('table'))
      ->addArgument('regex', InputArgument::OPTIONAL, 'Filter extensions by full key, short name, or description')
      ->setHelp('List extensions

Examples:
  cv ext:list
  cv ext:list --remote --dev /mail/
  cv ext:list /^org.civicrm.*/

Note:
  Short names ("foobar") do not work when passing an explicit URL.

  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.

  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.get`.
');
    parent::configureRepoOptions();
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $wo = ($input->getOption('out') === 'table')
      ? (OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_NORMAL)
      : (OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE);

    if ($input->getOption('local') || $input->getOption('remote')) {
      $local = (bool) $input->getOption('local');
      $remote = (bool) $input->getOption('remote');
    }
    else {
      $local = $remote = TRUE;
    }

    if ($extRepoUrl = $this->parseRepoUrl($input)) {
      global $civicrm_setting;
      $civicrm_setting['Extension Preferences']['ext_repo_url'] = $extRepoUrl;
    }

    $this->boot($input, $output);

    if ($remote) {
      $output->writeln("<info>Using extension feed \"" . \CRM_Extension_System::singleton()->getBrowser()->getRepositoryUrl() . "\"</info>", $wo);
    }

    if ($input->getOption('refresh')) {
      $output->writeln("<info>Refreshing extensions</info>", $wo);
      $result = $this->callApiSuccess($input, $output, 'Extension', 'refresh', array(
        'local' => $local,
        'remote' => $remote,
      ));
      if (!empty($result['is_error'])) {
        return 1;
      }
    }

    $columns = explode(',', $input->getOption('columns'));
    $records = $this->find($input->getArgument('regex'), $remote, $local);

    if ($input->getOption('out') === 'table') {
      $table = new Table($output);
      $table->setHeaders($columns);
      $table->addRows(ArrayUtil::convertAssocToNum($records, $columns));
      $table->render();
    }
    else {
      $this->sendResult($input, $output,
        ArrayUtil::filterColumns($records, $columns));
    }

    return 0;
  }

  /**
   * Get a list of all available extensions.
   *
   * @return array
   *   ($key => CRM_Extension_Info)
   */
  protected function getRemoteInfos() {
    static $cache = NULL;
    if ($cache === NULL) {
      $cache = \CRM_Extension_System::singleton()
        ->getBrowser()->getExtensions();
    }
    return $cache;
  }

  /**
   * @param string|NULL $regex
   *   Filter by regex.
   * @param bool $remote
   *   Include remote extensions.
   * @param bool $local
   *   Include local extensions.
   * @return array
   */
  protected function find($regex, $remote, $local) {
    $rows = array();

    if ($remote) {
      foreach ($this->getRemoteInfos() as $info) {
        $rows[] = array(
          'location' => 'remote',
          'key' => $info->key,
          'name' => $info->file,
          'version' => $info->version,
          'status' => '',
        );
      }
    }

    if ($local) {
      $keys = \CRM_Extension_System::singleton()->getFullContainer()->getKeys();
      $statuses = \CRM_Extension_System::singleton()->getManager()->getStatuses();
      foreach ($keys as $key) {
        $info = \CRM_Extension_System::singleton()
          ->getMapper()
          ->keyToInfo($key);
        $rows[] = array(
          'location' => 'local',
          'key' => $key,
          'name' => $info->file,
          'version' => $info->version,
          'status' => isset($statuses[$key]) ? $statuses[$key] : '',
        );
      }
    }

    if ($regex) {
      $rows = array_filter($rows, function ($row) use ($regex) {
        // Match on name or key.
        return preg_match($regex, $row['key']) || preg_match($regex, $row['name']);
      });
    }

    usort($rows, function ($a, $b) {
      // location, descending
      if ($a[0] < $b[0]) {
        return 1;
      }
      if ($a[0] > $b[0]) {
        return -1;
      }

      // name, ascending
      if ($a[1] < $b[1]) {
        return -1;
      }
      if ($a[1] > $b[1]) {
        return 1;
      }

      // key, ascending
      if ($a[2] < $b[2]) {
        return -1;
      }
      if ($a[2] > $b[2]) {
        return 1;
      }

      return 0;
    });

    return $rows;
  }

}