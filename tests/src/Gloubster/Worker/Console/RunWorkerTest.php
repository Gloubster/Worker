<?php

namespace Gloubster\Worker\Console;

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
                    "queue-name" => "Gloubster\\Queue::IMAGE_PROCESSING"
                )
            ),
            "log"        => array(
                "exchange-name" => "Gloubster\\Exchange::GLOUBSTER_DISPATCHER"
            )
        )));

        $application = new Application();
        $application->add(new RunWorker($conf, $logger));

        $command = $application->find('worker:run');

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
