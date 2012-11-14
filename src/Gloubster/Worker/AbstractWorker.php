<?php

namespace Gloubster\Worker;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use Gloubster\Exchange;
use Gloubster\RoutingKey;
use PhpAmqpLib\Message\AMQPMessage;
use Gloubster\Job\JobInterface;
use Gloubster\Exception\RuntimeException;
use Monolog\Logger;
use Neutron\TipTop\Clock;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;

abstract class AbstractWorker
{
    private $id;
    /**
     *
     * @var AMQPConnection
     */
    private $conn;
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

    public function __construct($id, AMQPConnection $conn, $queue, $logExchange, TemporaryFilesystem $filesystem, Logger $logger)
    {
        $this->id = $id;
        $this->queue = $queue;
        $this->logExchange = $logExchange;
        $this->conn = $conn;
        $this->channel = $conn->channel();
        $this->logger = $logger;
        $this->filesystem = $filesystem;

        declare(ticks=1);
        $clock = new Clock();
        $clock->addPeriodicTimer(1, array($this, 'sendPresence'));
        $this->sendPresence();
    }

    public function sendPresence()
    {
        $this->logger->addDebug( "sending presence\n");
        $this->channel->basic_publish(new AMQPMessage(serialize(array('hello'=>'world'))), Exchange::GLOUBSTER_DISPATCHER, RoutingKey::WORKER);
    }

    public function run($iterations = true)
    {
        while ($iterations) {
            $this->logger->addDebug(sprintf('Current memory usage : %s Mo', round(memory_get_usage() / (1024 * 1024),3)));
            $this->logger->addInfo('Waiting for jobs ...');
            try {
                $this->channel->basic_consume($this->queue, null, false, false, false, false, array($this, 'process'));

                while (count($this->channel->callbacks)) {
                    $read   = array($this->conn->getSocket()); // add here other sockets that you need to attend
                    $write  = null;
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

    public function process(AMQPMessage $message)
    {
        $this->logger->addInfo(sprintf('Processing job %s', $message->delivery_info['delivery_tag']));

        $job = unserialize($message->body);

        if (!$job instanceof JobInterface) {
            $this->logger->addCritical(sprintf('Received a wrong job message : %s', $message->body));
            $this->channel->basic_publish(new AMQPMessage($message->body), $this->logExchange, $this->queue . '.error');

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
        $this->channel->basic_publish(new AMQPMessage(serialize($message)), $this->logExchange, $this->queue . '.log');
    }
}
