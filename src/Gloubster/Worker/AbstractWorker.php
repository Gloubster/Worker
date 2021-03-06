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

use Doctrine\Common\Annotations\AnnotationRegistry;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Gloubster\Message\Presence\WorkerPresence;
use Gloubster\Configuration;
use Gloubster\Message\Job\JobInterface;
use Gloubster\Exception\InvalidArgumentException;
use Gloubster\Exception\RuntimeException;
use Gloubster\Worker\Job\Counter;
use Gloubster\Worker\Job\Result;
use Gloubster\Message\Factory as MessageFactory;
use MediaVorus\MediaVorus;
use Monolog\Logger;
use Neutron\TipTop\Clock;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

abstract class AbstractWorker
{
    private $id;
    private $channel;
    private $conn;
    private $queue;
    private $clock;
    private $running;
    private $jobCounter;
    private $mediavorus;
    /** @var Serializer */
    private $serializer;
    protected $filesystem;
    protected $logger;

    public function __construct($id, AMQPConnection $conn, $queue, TemporaryFilesystem $filesystem, Logger $logger)
    {
        $this->id = $id;
        $this->queue = $queue;
        $this->conn = $conn;
        $this->channel = $conn->channel();
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->jobCounter = new Counter();
        $this->mediavorus = MediaVorus::create();

        AnnotationRegistry::registerAutoloadNamespace(
            'JMS\Serializer\Annotation', __DIR__ . '/../../../vendor/jms/serializer/src'
        );

        $this->serializer = $serializer = SerializerBuilder::create()
            ->setCacheDir(__DIR__ . '/../../../cache')
            ->build();

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
        $presence = new WorkerPresence();
        $presence->setFailureJobs($this->jobCounter->getFailures())
            ->setId($this->id)
            ->setIdle(false)
            ->setLastJobTime($this->jobCounter->getUpdateTimestamp())
            ->setReportTime(microtime(true))
            ->setStartedTime($this->jobCounter->getStartTimestamp())
            ->setSuccessJobs($this->jobCounter->getSuccess())
            ->setTotalJobs($this->jobCounter->getTotal())
            ->setMemory(memory_get_usage())
            ->setWorkerType($this->getType());

        $this->channel->basic_publish(new AMQPMessage($presence->toJson()), Configuration::EXCHANGE_MONITOR);
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

        $error = false;

        try {
            $job = MessageFactory::fromJson($message->body);
            if (!$job instanceof JobInterface) {
                $error = true;
            }
        } catch (RuntimeException $e) {
            $error = true;
        }

        if ($error) {
            $this->logger->addCritical(sprintf('Received a wrong job message : %s', $message->body));
            $this->channel->basic_publish(new AMQPMessage($message->body), Configuration::EXCHANGE_DISPATCHER, Configuration::ROUTINGKEY_ERROR);
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

            $job->setResult($this->getTechnicalInformations($data));

            $start = microtime(true);
            $this->logger->addInfo(sprintf('Delivering job %s ...', $message->delivery_info['delivery_tag']));
            $this->deliver($job, $data);
            $this->logger->addInfo('Job delivered.');
            $job->setDeliveryDuration(microtime(true) - $start);
            $this->jobCounter->addSuccess();
        } catch (\Exception $e) {
            $error = $e;
            $job->setError(true);
            $job->setErrorMessage($e->getMessage());
        }

        if ($job->requireReceipt()) {
            $this->sendReceipt($job);
        }

        $job->setEnd(microtime(true));

        $this->log($job);

        $this->logger->addInfo(sprintf('Acknowledging %s', $message->delivery_info['delivery_tag']));
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

    private function getTechnicalInformations(Result $data)
    {
        $availableHashAlgos = hash_algos();

        if ($data->isPath()) {
            $result = json_decode(
                $this->serializer->serialize(
                    $this->mediavorus->guess($data->getData()), 'json'
                ), true
            );

            if (in_array('sha256', $availableHashAlgos)) {
                $result['sha256'] = hash_file('sha256', $data->getData());
            }
            if (in_array('sha256', $availableHashAlgos)) {
                $result['sha1'] = hash_file('sha1', $data->getData());
            }
            if (in_array('sha256', $availableHashAlgos)) {
                $result['md5'] = hash_file('md5', $data->getData());
            }
        } elseif ($data->isBinary()) {

            $temp = $this->filesystem->createEmptyFile(sys_get_temp_dir());
            file_put_contents($temp, $data->getData());

            $tcData = json_decode(
                $this->serializer->serialize(
                    $this->mediavorus->guess($temp), 'json'
                ), true
            );
            unlink($temp);

            if (in_array('sha256', $availableHashAlgos)) {
                $result['sha256'] = hash('sha256', $data->getData());
            }
            if (in_array('sha256', $availableHashAlgos)) {
                $result['sha1'] = hash('sha1', $data->getData());
            }
            if (in_array('sha256', $availableHashAlgos)) {
                $result['md5'] = hash('md5', $data->getData());
            }

            return $tcData;
        } else {
            throw new RuntimeException('Unable to extract technical informations');
        }

        return $result;
    }

    private function sendReceipt(JobInterface $job)
    {
        foreach ($job->getReceipts() as $receipt) {
            $receipt->acknowledge($job);
        }
    }

    private function log(JobInterface $message)
    {
        $this->channel->basic_publish(new AMQPMessage($message->toJson()), Configuration::EXCHANGE_DISPATCHER, Configuration::ROUTINGKEY_LOG);
    }
}
