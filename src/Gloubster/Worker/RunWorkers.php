<?php

namespace Gloubster\Worker;

use Gloubster\Delivery\Factory;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Neutron\System\CpuInfo;
use Spork\ProcessManager;
use Spork\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunWorkers extends Command
{

    public function __construct($name = null)
    {
        parent::__construct($name);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Configuration(file_get_contents(__DIR__ . '/../../../config/config.json'));

        $resolver = new SpawnResolver($configuration, CpuInfo::detect());

        $manager = new ProcessManager(new EventDispatcher());
        $factory = new Factory();
        $classname = $resolver->getClassName();

        if ($input->getOption('verbose')) {
            $outputLogger = new StreamHandler('php://stdout');
        } else {
            $outputLogger = new NullHandler();
        }

        for ($i = 1; $i <= $resolver->getSpawnQuantity(); $i ++ ) {

            $output->write("Launching Worker <info>$i</info> ...");

            $manager->fork(function() use ($configuration, $i, $factory, $classname, $outputLogger) {

                    $logger = new Logger('Worker-' . $i);
                    $logger->pushHandler($outputLogger);
                    $logger->pushHandler(new RotatingFileHandler(__DIR__ . '/../../../logs/worker-' . $i . '.logs', 3));

                    $worker = new Worker(new \GearmanWorker(), $logger);

                    foreach ($configuration['gearman-servers'] as $server) {
                        $worker->addServer($server['host'], $server['port']);
                    }

                    $worker->setFunction(new $classname($configuration, $logger, $factory));

                    $worker->run();
                });

            $output->writeln("Success !");
        }
    }
}
