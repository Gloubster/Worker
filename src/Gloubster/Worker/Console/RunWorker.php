<?php

namespace Gloubster\Worker\Console;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use Gloubster\Configuration;
use Gloubster\Exchange;
use Gloubster\RoutingKey;
use Gloubster\Worker\ImageWorker;
use Gloubster\Worker\Factory;
use Monolog\Logger;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class RunWorker extends Command
{
    private $logger;
    private $conn;
    private $conf;

    public function __construct(AMQPConnection $conn, Configuration $conf, Logger $logger)
    {
        parent::__construct('worker:run');

        $this->conn = $conn;
        $this->conf = $conf;
        $this->logger = $logger;
        $this->setDescription('Run a worker');

        $this->addArgument('type', InputArgument::REQUIRED, 'The worker type', null);
        $this->addArgument('id', InputArgument::OPTIONAL, 'The worker id', null);
        $this->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'The number of iterations', true);

        return $this;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        $id = $input->getArgument('id')? : $type . '-' . uniqid('', true);

        $output->writeln(sprintf("Running %s worker #%s ...", $type, $id));

        $worker = Factory::createWorker($type, $id, $this->conn, $this->conf, new TemporaryFilesystem(), $this->logger);
        $worker->run($input->getOption('iterations'));
    }
}