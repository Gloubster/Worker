<?php

namespace Gloubster\Worker\Console;

use Gloubster\Configuration;
use Gloubster\Worker\TestWorker;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/TestWorker.php';

class RunWorkerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers Gloubster\Worker\Console\RunWorker::execute
     */
    public function testExecute()
    {
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getMock();

        $conf = new Configuration(json_encode(array(
            "server" => array(
                "host"     => "localhost",
                "port"     => 5672,
                "user"     => "guest",
                "password" => "guest",
                "vhost"    => "/",
            ),
            "workers"  => array(
                "test" => array(
                    "queue-name" => "Gloubster\\Queue::IMAGE_PROCESSING"
                )
            ),
            "log"       => array(
                "exchange-name" => "Gloubster\\Exchange::GLOUBSTER_DISPATCHER"
            )
        )));

        $application = new Application();
        $application->add(new RunWorker($conf, $logger));

        $command = $application->find('worker:run');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'type'=>'test',
            '--iterations'=>5
        ));

        $this->assertEquals(5, TestWorker::$iterations);
    }

    private function getConnection()
    {
        return $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getChannel()
    {
        return $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
