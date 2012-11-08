<?php

namespace Gloubster\Worker;

use Gloubster\Configuration;

class FactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers Gloubster\Worker\Factory::createWorker
     * @expectedException Gloubster\Exception\InvalidArgumentException
     */
    public function testCreateWorkerWithWrongLogEchange()
    {
        $configuration = new Configuration(json_encode(array(
                    "server" => array(
                        "host"     => "localhost",
                        "port"     => 5672,
                        "user"     => "guest",
                        "password" => "guest",
                        "vhost"    => "/",
                    ),
                    "workers"  => array(
                        "image" => array(
                            "queue-name" => 'Gloubster\\Queue::IMAGE_PROCESSING',
                        )
                    ),
                    "log" => array(
                        "exchange-name"=>'Gloubster\\Exchange::NOLOGS'
                    )
                )), array(
                file_get_contents(__DIR__ . '/../../../../resources/configuration.schema.json')
            ));

        $channel = $this->getMockBuilder('PhpAmqpLib\\Channel\\AMQPChannel')
            ->disableOriginalConstructor()
            ->getmock();
        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        Factory::createWorker("image", "image-001", $channel, $configuration, $filesystem, $logger);
    }

    /**
     * @covers Gloubster\Worker\Factory::createWorker
     * @expectedException Gloubster\Exception\InvalidArgumentException
     */
    public function testCreateWorkerWithWrongQueueName()
    {
        $configuration = new Configuration(json_encode(array(
                    "server" => array(
                        "host"     => "localhost",
                        "port"     => 5672,
                        "user"     => "guest",
                        "password" => "guest",
                        "vhost"    => "/",
                    ),
                    "workers"  => array(
                        "image" => array(
                            "queue-name" => 'Gloubster\\Queue::CATS_PROCESSING',
                        )
                    ),
                    "log" => array(
                        "exchange-name"=>'Gloubster\\Exchange::LOGS'
                    )
                )), array(
                file_get_contents(__DIR__ . '/../../../../resources/configuration.schema.json')
            ));

        $channel = $this->getMockBuilder('PhpAmqpLib\\Channel\\AMQPChannel')
            ->disableOriginalConstructor()
            ->getmock();
        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        Factory::createWorker("image", "image-001", $channel, $configuration, $filesystem, $logger);
    }

    /**
     * @covers Gloubster\Worker\Factory::createWorker
     * @expectedException Gloubster\Exception\InvalidArgumentException
     */
    public function testCreateWorkerThatDoesNotExists()
    {
        $configuration = new Configuration(json_encode(array(
                    "server" => array(
                        "host"     => "localhost",
                        "port"     => 5672,
                        "user"     => "guest",
                        "password" => "guest",
                        "vhost"    => "/",
                    ),
                    "workers"  => array(
                        "imagine" => array(
                            "queue-name" => 'Gloubster\\Queue::IMAGE_PROCESSING',
                        )
                    ),
                    "log" => array(
                        "exchange-name"=>'Gloubster\\Exchange::LOGS'
                    )
                )), array(
                file_get_contents(__DIR__ . '/../../../../resources/configuration.schema.json')
            ));

        $channel = $this->getMockBuilder('PhpAmqpLib\\Channel\\AMQPChannel')
            ->disableOriginalConstructor()
            ->getmock();
        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        Factory::createWorker("image", "image-001", $channel, $configuration, $filesystem, $logger);
    }

    /**
     * @covers Gloubster\Worker\Factory::createWorker
     */
    public function testCreateImageWorker()
    {
        $configuration = new Configuration(json_encode(array(
                    "server" => array(
                        "host"     => "localhost",
                        "port"     => 5672,
                        "user"     => "guest",
                        "password" => "guest",
                        "vhost"    => "/",
                    ),
                    "workers"  => array(
                        "image" => array(
                            "queue-name" => 'Gloubster\\Queue::IMAGE_PROCESSING',
                        )
                    ),
                    "log" => array(
                        "exchange-name"=>'Gloubster\\Exchange::LOGS'
                    )
                )), array(
                file_get_contents(__DIR__ . '/../../../../resources/configuration.schema.json')
            ));

        $channel = $this->getMockBuilder('PhpAmqpLib\\Channel\\AMQPChannel')
            ->disableOriginalConstructor()
            ->getmock();
        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        $worker = Factory::createWorker("image", "image-001", $channel, $configuration, $filesystem, $logger);

        $this->assertInstanceOf('Gloubster\\Worker\\ImageWorker', $worker);
    }
}
