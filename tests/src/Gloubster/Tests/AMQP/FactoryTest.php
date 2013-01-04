<?php

namespace Gloubster\Tests\AMQP;

use Gloubster\Configuration;
use Gloubster\AMQP\Factory;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    private $configuration;

    public function setUp()
    {
        parent::setUp();

        $this->configuration = new Configuration(file_get_contents(__DIR__ . '/../../../../resources/config.tests.json'));
    }

    /**
     * @covers Gloubster\AMQP\Factory::createAMQPLibConnection
     */
    public function testCreateConnectedConnection()
    {
        $connection = Factory::createAMQPLibConnection($this->configuration);
        $this->assertInstanceOf('PhpAmqpLib\Connection\AMQPConnection', $connection);
    }
}
