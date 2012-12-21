<?php

namespace Gloubster\Tests\Worker;

use Gloubster\Worker\Factory;
use Gloubster\Configuration;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    private function getConnection()
    {
        $channel = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
            ->disableOriginalConstructor()
            ->getmock();

        $conn = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
            ->disableOriginalConstructor()
            ->getmock();

        $conn->expects($this->any())
            ->method('channel')
            ->will($this->returnValue($channel));

        return $conn;
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
                        "queue-name" => 'Gloubster\\RabbitMQ\\Configuration::QUEUE_CATS_PROCESSING',
                    )
                )
            )), array(
            file_get_contents(__DIR__ . '/../../../../../resources/configuration.schema.json')
        ));

        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        Factory::createWorker("image", "image-001", $this->getConnection(), $configuration, $filesystem, $logger);
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
                        "queue-name" => 'Gloubster\\RabbitMQ\\Configuration::QUEUE_IMAGE_PROCESSING',
                    )
                )
            )), array(
            file_get_contents(__DIR__ . '/../../../../../resources/configuration.schema.json')
        ));

        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        Factory::createWorker("image", "image-001", $this->getConnection(), $configuration, $filesystem, $logger);
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
                        "queue-name" => 'Gloubster\\RabbitMQ\\Configuration::QUEUE_IMAGE_PROCESSING',
                    )
                )
            )), array(
            file_get_contents(__DIR__ . '/../../../../../resources/configuration.schema.json')
        ));

        $filesystem = $this->getMockBuilder('Neutron\\TemporaryFilesystem\\TemporaryFilesystem')
            ->disableOriginalConstructor()
            ->getmock();
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getmock();

        $worker = Factory::createWorker("image", "image-001", $this->getConnection(), $configuration, $filesystem, $logger);

        $this->assertInstanceOf('Gloubster\\Worker\\ImageWorker', $worker);
    }
}
