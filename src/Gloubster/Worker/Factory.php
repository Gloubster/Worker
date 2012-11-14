<?php

namespace Gloubster\Worker;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use Gloubster\Configuration;
use Gloubster\RabbitMQFactory;
use Gloubster\Exception\InvalidArgumentException;
use Monolog\Logger;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;

class Factory
{

    public static function createWorker($type, $id, AMQPConnection $conn, Configuration $configuration, TemporaryFilesystem $filesystem, Logger $logger)
    {
        if (!isset($configuration['workers'][$type])) {
            throw new InvalidArgumentException(sprintf('Worker %s is not defined', $type));
        }

        $worker = $configuration['workers'][$type];

        $classname = 'Gloubster\\Worker\\' . ucfirst($type) . 'Worker';

        if (!defined($worker['queue-name'])) {
            throw new InvalidArgumentException('Invalid queue name');
        }
        if (!defined($configuration['log']['exchange-name'])) {
            throw new InvalidArgumentException('Invalid log exchange-name');
        }

        $queueName = (string) constant($worker['queue-name']);
        $logExchange = (string) constant($configuration['log']['exchange-name']);

        return new $classname($id, $conn, $queueName, $logExchange, $filesystem, $logger);
    }
}
