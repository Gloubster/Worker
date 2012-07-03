<?php

namespace Gloubster\Gearman;

use Monolog\Handler\NullHandler;
use Monolog\Logger;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Worker
     */
    protected $object;
    protected $gearmanWorker;

    /**
     * @covers Gloubster\Gearman\Worker::__construct
     */
    protected function setUp()
    {
        $logger = new Logger('test worker');
        $logger->pushHandler(new NullHandler());
        $this->gearmanWorker = $this->getMock('\\GearmanWorker');
        $this->object = new Worker($this->gearmanWorker, $logger);
    }

    /**
     * @covers Gloubster\Gearman\Worker::addServer
     */
    public function testAddServer()
    {
        $host = 'kangaroos-kenobi';
        $port = 23400;

        $that = $this;
        $this->gearmanWorker->expects($this->once())
            ->method('addServer')
            ->will($this->returnCallback(function($hostcalled, $portcalled) use ($that, $host, $port) {
                        $that->assertEquals($port, $portcalled);
                        $that->assertEquals($host, $hostcalled);
                    }));

        $this->object->addServer($host, $port);
    }

    /**
     * @covers Gloubster\Gearman\Worker::addFunction
     */
    public function testAddFunction()
    {
        $registered_function = 'goldmember';
        $that = $this;
        $this->gearmanWorker->expects($this->once())
            ->method('addFunction')
            ->will($this->returnCallback(function($function, $callable) use ($that, $registered_function) {
                        $that->assertEquals($registered_function, $function);
                        $that->assertTrue(is_callable($callable));
                    }));

        $function = $this->getMock('\\Gloubster\\Gearman\\Functions\\FunctionInterface', array('execute', 'getFunctionName'));

        $function->expects($this->any())
            ->method('getFunctionName')
            ->will($this->returnValue($registered_function));

        $this->object->addFunction($function);
    }

    /**
     * @covers Gloubster\Gearman\Worker::run
     * @todo Implement testRun().
     */
    public function testRun()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers Gloubster\Gearman\Worker::ping
     */
    public function testPing()
    {
        $this->gearmanWorker->expects($this->any())
            ->method('echo')
            ->will($this->returnCallback(function($string) {
                        return $string;
                    }));

        $this->assertTrue($this->object->ping());
    }

    /**
     * @covers Gloubster\Gearman\Worker::ping
     */
    public function testPingFailed()
    {
        $this->gearmanWorker->expects($this->any())
            ->method('echo')
            ->will($this->returnCallback(function($string) {
                        return $string;
                    }));

        $this->assertTrue($this->object->ping());
    }
}
