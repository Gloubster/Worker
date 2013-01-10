<?php

/*
 * This file is part of Gloubster.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gloubster\Worker;

use PhpAmqpLib\Connection\AMQPConnection;
use Gloubster\Configuration;
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
            throw new InvalidArgumentException(sprintf('Invalid queue name : %s', $worker['queue-name']));
        }

        $queueName = (string) constant($worker['queue-name']);

        return new $classname($id, $conn, $queueName, $filesystem, $logger);
    }
}
