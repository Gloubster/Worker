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
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getMock();

        $conf = new Configuration(file_get_contents(__DIR__ . '/../../../../../resources/config.tests.json'));

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
