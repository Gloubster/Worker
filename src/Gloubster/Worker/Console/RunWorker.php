<?php

/*
 * This file is part of Gloubster.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gloubster\Worker\Console;

use Gloubster\Configuration;
use Gloubster\Worker\Factory;
use Gloubster\RabbitMQ\Factory as RabbitMQFactory;
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
    private $conf;

    public function __construct(Configuration $conf, Logger $logger)
    {
        parent::__construct('run');

        $this->conf = $conf;
        $this->logger = $logger;
        $this->setDescription('Run a worker');

        $this->addArgument('type', InputArgument::REQUIRED, 'The worker type', null);
        $this->addArgument('id', InputArgument::OPTIONAL, 'The worker id', null);
        $this->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'The number of iterations', true);
        $this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'The worker timeout', false);

        return $this;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = RabbitMQFactory::createAMQPLibConnection($this->conf);

        $type = $input->getArgument('type');
        $id = $input->getArgument('id') ?: $this->generateId($type);

        $output->writeln(sprintf("Running %s worker #%s ...", $type, $id));

        $worker = Factory::createWorker($type, $id, $conn, $this->conf, new TemporaryFilesystem(), $this->logger);

        if (0 < (int) $input->getOption('timeout')) {
            $worker->setTimeout($input->getOption('timeout'));
        }

        $worker->run($input->getOption('iterations'));
    }

    private function generateId($type)
    {
        return $type . '-' . uniqid('', true);
    }
}
