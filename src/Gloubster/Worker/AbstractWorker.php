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

use Gloubster\Monitor\Worker\Presence;
use Gloubster\Exchange;
use Gloubster\RoutingKey;
use Gloubster\Job\JobInterface;
use Gloubster\Exception\InvalidArgumentException;
use Gloubster\Exception\RuntimeException;
use Gloubster\Worker\Job\Counter;
use Gloubster\Worker\Job\Result;
use Monolog\Logger;
use Neutron\TipTop\Clock;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

abstract class AbstractWorker
{
    private $id;
    private $channel;
    private $queue;
    private $clock;
    private $running;
    private $jobCounter;
    protected $filesystem;
    protected $logger;

    final public function __construct($id, AMQPConnection $conn, $queue, TemporaryFilesystem $filesystem, Logger $logger)
    {
        $this->id = $id;
        $this->queue = $queue;
        $this->conn = $conn;
        $this->channel = $conn->channel();
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->jobCounter = new Counter();
        $this->running = false;

        declare(ticks = 1);
        $this->clock = new Clock();
        $this->clock->addPeriodicTimer(1, array($this, 'sendPresence'));
    }

    final public function setTimeout($time)
    {
        if ($time < 1) {
            throw new InvalidArgumentException('Timeout must be a positive integer');
        }

        $this->clock->addTimer($time, array($this, 'stop'));
    }

    final public function stop()
    {
        $this->running = false;
    }

    final public function sendPresence()
    {
        $presence = new Presence();
        $presence->setFailureJobs($this->jobCounter->getFailures())
            ->setId($this->id)
            ->setIdle(false)
            ->setLastJobTime($this->jobCounter->getUpdateTimestamp())
            ->setReportTime(microtime(true))
            ->setStartedTime($this->jobCounter->getStartTimestamp())
            ->setSuccessJobs($this->jobCounter->getSuccess())
            ->setTotalJobs($this->jobCounter->getTotal())
            ->setMemory(memory_get_usage());

        $this->channel->basic_publish(new AMQPMessage(serialize($presence)), Exchange::GLOUBSTER_DISPATCHER, RoutingKey::WORKER);
    }

    final public function run($iterations = true)
    {
        $this->running = true;

        while ($this->running && $iterations) {
            $this->logger->addInfo('Waiting for jobs ...');
            try {
                $this->channel->basic_consume($this->queue, null, false, false, false, false, array($this, 'process'));

                while ($this->running && count($this->channel->callbacks)) {
                    $read = array($this->conn->getSocket());
                    $write = null;
                    $except = null;
                    if (false === ($num_changed_streams = @stream_select($read, $write, $except, 60))) {
                        /* Error handling */
                    } elseif ($num_changed_streams > 0) {
                        $this->channel->wait();
                    }
                }
            } catch (\Exception $e) {
                $this->logger->addError(sprintf('Process failed : %s', $e->getMessage()));
            }
            $this->logger->addInfo('Job finished');
            $iterations--;
        }

        return $this;
    }

    final public function process(AMQPMessage $message)
    {
        $this->logger->addInfo(sprintf('Processing job %s', $message->delivery_info['delivery_tag']));

        $job = unserialize($message->body);

        if (!$job instanceof JobInterface) {
            $this->logger->addCritical(sprintf('Received a wrong job message : %s', $message->body));
            $this->channel->basic_publish(new AMQPMessage($message->body), Exchange::GLOUBSTER_DISPATCHER, RoutingKey::ERROR);

            $this->channel->basic_ack($message->delivery_info['delivery_tag']);

            throw new RuntimeException('Wrong job message');
        }

        $error = null;

        try {
            $this->jobCounter->add();
            $job->setWorkerId($this->id);
            assert($job->isOk(true));

            $start = microtime(true);
            $this->logger->addInfo(sprintf('Computing job %s ...', $message->delivery_info['delivery_tag']));
            $data = $this->compute($job);
            $this->logger->addInfo('Job computed.');
            $job->setProcessDuration(microtime(true) - $start);

            $start = microtime(true);
            $this->logger->addInfo(sprintf('Delivering job %s ...', $message->delivery_info['delivery_tag']));
            $this->deliver($job, $data);
            $this->logger->addInfo('Job delivered.');
            $job->setDeliveryDuration(microtime(true) - $start);
            $this->jobCounter->addSuccess();
        } catch (\Exception $e) {
            $error = $e;
            $job->setError(true);
        }

        if ($job->requireReceipt()) {
            $this->sendReceipt($job);
        }

        $job->setEnd(microtime(true));

        $this->log($job);

        $this->channel->basic_ack($message->delivery_info['delivery_tag']);

        if ($error) {
            throw $error;
        }
    }

    abstract public function getType();

    /**
     * @return string The path to the file or binary data
     */
    abstract public function compute(JobInterface $job);

    private function deliver(JobInterface $job, Result $data)
    {
        if ($data->isPath()) {
            $job->getDelivery()->deliverFile($data->getData());
        } elseif ($data->isBinary()) {
            $job->getDelivery()->deliverBinary($data->getData());
        } else {
            throw new RuntimeException(sprintf('Result `%s` is not known', $data->getType()));
        }

        return $this;
    }

    private function log(JobInterface $message)
    {
        $this->channel->basic_publish(new AMQPMessage(serialize($message)), Exchange::GLOUBSTER_DISPATCHER, RoutingKey::LOG);
    }
}
