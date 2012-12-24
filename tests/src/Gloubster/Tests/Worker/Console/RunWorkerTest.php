<?php

namespace Gloubster\Tests\Worker\Console;

use Gloubster\Worker\Console\RunWorker;
use Gloubster\Configuration;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RunWorkerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Gloubster\Worker\Console\RunWorker::execute
     */
    public function testExecute()
    {
        $this->markTestSkipped('Currently skipped');
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
                "image" => array(
                    "queue-name" => "Gloubster\\RabbitMQ\\Configuration::QUEUE_IMAGE_PROCESSING"
                )
            )
        )));

        $application = new Application();
        $application->add(new RunWorker($conf, $logger));

        $command = $application->find('run');

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'      => $command->getName(),
            'type'         => 'image',
            '--iterations' => 5,
            '--timeout'    => 2
        ));
    }

    private function getChannel()
    {
        return $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
                ->disableOriginalConstructor()
                ->getMock();
    }
}
