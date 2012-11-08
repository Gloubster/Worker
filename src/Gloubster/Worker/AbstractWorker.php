<?php

namespace Gloubster\Worker;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Gloubster\Job\JobInterface;
use Gloubster\Exception\RuntimeException;
use Monolog\Logger;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;

abstract class AbstractWorker
{
    private $id;
    private $connection;
    private $channel;
    private $queue;
    private $logExchange;

    /**
     *
     * @var TemporaryFilesystem
     */
    protected $filesystem;

    /**
     *
     * @var Logger
     */
    protected $logger;

    public function __construct($id, AMQPChannel $channel, $queue, $logExchange, TemporaryFilesystem $filesystem, Logger $logger)
    {
        $this->id = $id;
        $this->queue = $queue;
        $this->logExchange = $logExchange;
        $this->channel = $channel;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
    }

    public function run($iterations = true)
    {
        while ($iterations) {
            $this->logger->addDebug(sprintf('Current memory usage : %s Mo', round(memory_get_usage() / (1024 * 1024),3)));
            try {
                $this->logger->addInfo('Waiting for jobs ...');
                $this->channel->basic_consume($this->queue, null, false, true, false, false, array($this, 'process'));

                while (count($this->channel->callbacks)) {
                    $this->channel->wait();
                }
            } catch (\Exception $e) {
                $this->logger->addError(sprintf('Process failed : %s', $e->getMessage()));
            }
            $iterations--;
        }

        return $this;
    }

    public function process(AMQPMessage $message)
    {
        $this->logger->addInfo(sprintf('Processing job %s', $message->delivery_info['delivery_tag']));

        $job = unserialize($message->body);

        if (!$job instanceof JobInterface) {
            $this->logger->addCritical(sprintf('Received a wrong job message : %s', $message->body));
            $this->channel->basic_publish($message->body, $this->logExchange, $this->queue . '.error');
            $this->channel->basic_ack($message->delivery_info['delivery_tag']);

            throw new RuntimeException('Wrong job message');
        }

        $error = null;

        try {
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

    abstract public function compute(JobInterface $job);

    private function deliver(JobInterface $job, $data)
    {
        if (is_file($data)) {
            $job->getDelivery()->deliverFile($data);
        } else {
            $job->getDelivery()->deliverBinary($data);
        }

        return $this;
    }

    private function log(JobInterface $message)
    {
        $this->channel->basic_publish(serialize($message), $this->logExchange, $this->queue . '.log');
    }
}
