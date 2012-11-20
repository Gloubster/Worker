<?php

namespace Gloubster\Worker;

use Gloubster\Exception\RuntimeException;
use Gloubster\Delivery\DeliveryInterface;
use Gloubster\Delivery\FileSystem;
use Gloubster\Exchange;
use Gloubster\RoutingKey;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

abstract class AbstractWorkerTest extends \PHPUnit_Framework_TestCase
{
    protected $object;
    protected $target;

    protected function setUp()
    {
        parent::setUp();
        $this->target = __DIR__ . '/../../target' . microtime(true) . 'test';

        if (file_exists($this->target)) {
            unlink($this->target);
        }
    }

    protected function tearDown()
    {
        if (file_exists($this->target)) {
            unlink($this->target);
        }
        parent::tearDown();
    }

    public function testRun()
    {
        $conn = $this->getConnection();
        $channel = $this->getChannel();

        $channel->expects($this->once())
            ->method('basic_consume');

        $conn->expects($this->once())
            ->method('channel')
            ->will($this->returnValue($channel));

        $worker = $this->getMockForAbstractClass('Gloubster\\Worker\\AbstractWorker', array(
            $this->getId(),
            $conn,
            $this->getQueueName(),
            new TemporaryFilesystem(),
            $this->getLogger()
        ));

        $worker->run(1);
    }

    public function testRun5Iterations()
    {
        $conn = $this->getConnection();

        $channel = $this->getChannel();
        $channel->expects($this->exactly(5))
            ->method('basic_consume');

        $conn->expects($this->once())
            ->method('channel')
            ->will($this->returnValue($channel));

        $worker = $this->getMockForAbstractClass('Gloubster\\Worker\\AbstractWorker', array(
            $this->getId(),
            $conn,
            $this->getQueueName(),
            new TemporaryFilesystem(),
            $this->getLogger()
        ));

        $worker->run(5);
    }

    public function testGetType()
    {
        $this->assertInternalType('string', $this->getWorker()->getType());
    }

    public function testWrongProcess()
    {
        $wrongData = serialize(array('hello' => 'world'));

        $message = $this->getAMQPMessage($wrongData);
        $channel = $this->getChannel();
        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);

        $that = $this;

        $this->probePublishValues($channel, null, RoutingKey::ERROR,
            function($message) use ($wrongData, $that) {
                $that->assertEquals($wrongData, $message->body);
            }
        );

        try {
            $this->getWorker($channel)->process($message);
            $this->fail('Should have thrown an exception');
        } catch (RuntimeException $e) {

        }
    }

    public function testProcess()
    {
        $job = $this->getJob(new FileSystem($this->target));
        $this->assertTrue($job->isOk());

        $message = $this->getAMQPMessage(serialize($job));
        $channel = $this->getChannel();

        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);
        $this->probePublishValues($channel, true, RoutingKey::LOG);

        $this->getWorker($channel)->process($message);

        $this->assertTrue(file_exists($this->target));
    }

    public function testProcessFailed()
    {
        $job = $this->getJob(new FileSystem($this->target));
        $this->assertTrue($job->isOk());

        $message = $this->getAMQPMessage(serialize($job));
        $conn = $this->getConnection();
        $channel = $this->getChannel();

        $conn->expects($this->once())
            ->method('channel')
            ->will($this->returnValue($channel));

        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);
        $this->probePublishValues($channel, false, RoutingKey::LOG);

        $exception = new \Exception('Plouf');

        $worker = $this->getMock(
            'Gloubster\\Worker\\AbstractWorker',
            array('getType', 'compute'),
            array($this->getId(), $conn, $this->getQueueName(), new TemporaryFilesystem(), $this->getLogger())
        );

        $worker->expects($this->once())
            ->method('compute')
            ->will($this->throwException($exception));

        try {
            $worker->process($message);
            $this->fail('Should throw the exception');
        } catch (\Exception $e) {
            $this->assertEquals($exception, $e);
        }
    }

    public function testProcessWithWrongJobType()
    {
        $job = $this->getWrongJob(new FileSystem($this->target));
        $this->assertTrue($job->isOk());

        $message = $this->getAMQPMessage(serialize($job));
        $channel = $this->getChannel();

        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);
        $this->probePublishValues($channel, false, RoutingKey::LOG);

        try {
            $this->getWorker($channel)->process($message);
            $this->fail('Should have raised an exception');
        } catch (RuntimeException $e) {

        }
    }

    public function testSendPresenceRegularly()
    {
        $collector = array();

        $channel = $this->getChannel();
        $channel->expects($this->any())
            ->method('basic_publish')
            ->will($this->returnCallback(function($message) use (&$collector) {
                $collector[] = $message;
            }));

        $worker = $this->getWorker($channel);
        $worker->setTimeout(3);
        $worker->run();

        $this->assertGreaterThanOrEqual(2, count($collector));

        foreach($collector as $message) {
            $job = unserialize($message->body);
            $this->assertInstanceOf('Gloubster\Monitor\Worker\Presence', $job);
        }
    }

    private function probePublishValues(AMQPChannel $channel, $good, $expectedQueueName, $callback = null)
    {
        $that = $this;

        $channel->expects($this->any())
            ->method('basic_publish')
            ->with($this->isInstanceOf('PhpAmqpLib\Message\AMQPMessage'),
                    $this->isType('string'),
                    $this->isType('string'))
            ->will($this->returnCallback(
                function($message, $exchangeName, $routingKey)
                    use ($that, $good, $expectedQueueName, $callback) {

                    $that->assertEquals(Exchange::GLOUBSTER_DISPATCHER, $exchangeName);
                    $that->assertEquals($expectedQueueName, $routingKey);

                    if ($good === true) {
                        $that->assertGoodLogJob($message);
                        $that->assertGoodLocalLogJob($message);
                    } elseif ($good === false) {
                        $that->assertGoodLogWrongJob($message);
                        $that->assertWrongLocalLogJob($message);
                    }

                    if ($callback) {
                        $callback($message);
                    }
                }
            ));
    }

    protected function assertGoodLogJob(AMQPMessage $message)
    {
        $job = unserialize($message->body);

        $this->assertGreaterThan(0, $job->getBeginning());
        $this->assertGreaterThan(0, $job->getEnd());
        $this->assertGreaterThan($job->getBeginning(), $job->getEnd());
        $this->assertGreaterThan(0, $job->getDeliveryDuration());
        $this->assertGreaterThan(0, $job->getProcessDuration());
        $this->assertFalse($job->isOnError());
        $this->assertInstanceOf('Gloubster\\Delivery\\DeliveryInterface', $job->getDelivery());
        $this->assertEquals($this->getId(), $job->getWorkerId());
    }

    protected function assertGoodLogWrongJob(AMQPMessage $message)
    {
        $job = unserialize($message->body);

        $this->assertInstanceOf('Gloubster\\Job\\JobInterface', $job);

        $this->assertGreaterThan(0, $job->getBeginning());
        $this->assertGreaterThan(0, $job->getEnd());
        $this->assertGreaterThan($job->getBeginning(), $job->getEnd());
        $this->assertTrue($job->isOnError());
        $this->assertInstanceOf('Gloubster\\Delivery\\DeliveryInterface', $job->getDelivery());
        $this->assertEquals($this->getId(), $job->getWorkerId());
    }

    private function ensureAcknowledgement(AMQPChannel $channel, $deliveryTag)
    {
        $channel->expects($this->once())
            ->method('basic_ack')
            ->with($this->equalTo($deliveryTag));
    }

    protected function getAMQPMessage($data)
    {
        $delivery_tag = mt_rand(10000000, 99999999);
        $message = new AMQPMessage($data);
        $message->delivery_info = array(
            "delivery_tag" => $delivery_tag,
        );

        return $message;
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

    private function getLogger()
    {
        return $this->getMockBuilder('Monolog\\Logger')
                ->disableOriginalConstructor()
                ->getMock();
    }

    abstract public function testCompute();

    abstract public function assertGoodLocalLogJob(AMQPMessage $message);

    abstract public function assertWrongLocalLogJob(AMQPMessage $message);

    abstract public function getId();

    abstract public function getQueueName();

    abstract public function getWorker(AMQPChannel $channel = null);

    abstract public function getJob(DeliveryInterface $delivery);

    abstract public function getWrongJob(DeliveryInterface $delivery);
}
