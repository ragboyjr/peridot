<?php
namespace Peridot\Console;

use Evenement\EventEmitterInterface;
use Peridot\Configuration;
use Peridot\Core\SpecResult;
use Peridot\Reporter\ReporterFactory;
use Peridot\Runner\Context;
use Peridot\Runner\Runner;
use Peridot\Runner\SuiteLoader;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Command
 * @package Peridot\Console
 */
class Command extends ConsoleCommand
{
    /**
     * @var \Evenement\EventEmitterInterface
     */
    protected $eventEmitter;

    /**
     * Constructor
     */
    public function __construct(EventEmitterInterface $eventEmitter)
    {
        parent::__construct('peridot');
        $this->eventEmitter = $eventEmitter;
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = ConfigurationReader::readInput($input);
        $runner = new Runner(Context::getInstance()->getCurrentSuite(), $configuration, $this->eventEmitter);
        $factory = new ReporterFactory($configuration, $runner, $output, $this->eventEmitter);

        if (file_exists($configuration->getConfigurationFile())) {
            $this->loadConfiguration($this->eventEmitter, $configuration, $factory);
        }

        if ($input->getOption('reporters')) {
            $this->listReporters($factory, $output);

            return 0;
        }

        //Defer selection of reporter to account for user registered reporters
        if ($reporter = $input->getOption('reporter')) {
            $configuration->setReporter($reporter);
        }

        $result = new SpecResult($this->eventEmitter);
        $loader = new SuiteLoader($configuration->getGrep());
        $loader->load($configuration->getPath());
        $factory->create($configuration->getReporter());
        $runner->run($result);

        if ($result->getFailureCount() > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * Output available reporters
     *
     * @param ReporterFactory $factory
     * @param OutputInterface $output
     */
    protected function listReporters(ReporterFactory $factory, OutputInterface $output)
    {
        $output->writeln("");
        foreach ($factory->getReporters() as $name => $info) {
            $output->writeln(sprintf("    %s - %s", $name, $info['description']));
        }
        $output->writeln("");
    }

    /**
     * Load configuration file. If the configuration file returns
     * a callable, it will be executed with the runner, configuration, and reporter factory
     *
     * @param EventEmitterInterface $emitter
     * @param Configuration         $configuration
     * @param ReporterFactory       $reporters
     */
    protected function loadConfiguration(EventEmitterInterface $emitter, Configuration $configuration, ReporterFactory $reporters)
    {
        $func = include $configuration->getConfigurationFile();
        if (is_callable($func)) {
            $func($emitter, $configuration, $reporters);
        }
    }
}
