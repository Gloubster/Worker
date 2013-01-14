<?php

namespace Gloubster\Tests\Worker;

use Gloubster\Configuration;
use Gloubster\Delivery\DeliveryInterface;
use Gloubster\Delivery\Filesystem;
use Gloubster\Exception\RuntimeException;
use Gloubster\Message\Factory as MessageFactory;
use Gloubster\Message\Job\JobInterface;
use Gloubster\Message\Presence\WorkerPresence;
use Gloubster\Worker\Job\Result;
use Gloubster\Receipt\AbstractReceipt;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

require_once __DIR__ . '/../DeliveryBinary/DeliveryBinary.php';

abstract class AbstractWorkerTest extends \PHPUnit_Framework_TestCase
{
    protected $object;
    protected $target;

    protected function setUp()
    {
        parent::setUp();
        $this->target = __DIR__ . '/../../../target' . microtime(true) . 'test';

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
        $wrongData = json_encode(array('type' => 'Gloubster\\Tests\\Worker\\FakeSpace\\NonImageJob'));

        $message = $this->getAMQPMessage($wrongData);
        $channel = $this->getChannel();
        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);

        $that = $this;

        $this->probePublishValues($channel, null, Configuration::ROUTINGKEY_ERROR,
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
        $pathReceipt = tempnam(sys_get_temp_dir(), 'receipt');
        unlink($pathReceipt);

        $receipt = new TestReceipt();
        $receipt->setpath($pathReceipt);

        $job = $this->getJob(Filesystem::create($this->target));
        $job->addReceipt($receipt);

        $this->assertTrue($job->isOk());

        $message = $this->getAMQPMessage($job->toJson());
        $channel = $this->getChannel();

        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);
        $this->probePublishValues($channel, true, Configuration::ROUTINGKEY_LOG);

        $this->getWorker($channel)->process($message);

        $this->assertTrue(file_exists($this->target));
        $this->assertTrue(file_exists($pathReceipt));

        $job = MessageFactory::fromJson(file_get_contents($pathReceipt));
        $this->assertInstanceOf('Gloubster\Message\Job\JobInterface', $job);
        unlink($pathReceipt);
    }

    public function testProcessWithDeliveryBinary()
    {
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getMock();

        $job = $this->getJob(new \Gloubster\Delivery\DeliveryBinary());

        $message = $this->getAMQPMessage($job->toJson());
        $channel = $this->getChannel();

        $conn = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
            ->disableOriginalConstructor()
            ->getmock();

        $result = new Result(Result::TYPE_BINARYSTRING, 'binary-data');

        $conn->expects($this->any())
            ->method('channel')
            ->will($this->returnValue($channel));

        $worker = $this->getMock('Gloubster\Worker\AbstractWorker', array('compute', 'getType'), array('id', $conn, 'queue', new TemporaryFilesystem, $logger));

        $worker->expects($this->any())
            ->method('compute')
            ->will($this->returnValue($result));


        $worker->process($message);
    }

    public function testProcessWithWrongDeliveryResult()
    {
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getMock();

        $job = $this->getJob(new \Gloubster\Delivery\DeliveryBinary());

        $message = $this->getAMQPMessage($job->toJson());
        $channel = $this->getChannel();

        $conn = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
            ->disableOriginalConstructor()
            ->getmock();

        $result = new Result('no-type', 'wrong-data');

        $conn->expects($this->any())
            ->method('channel')
            ->will($this->returnValue($channel));

        $worker = $this->getMock('Gloubster\Worker\AbstractWorker', array('compute', 'getType'), array('id', $conn, 'queue', new TemporaryFilesystem, $logger));

        $worker->expects($this->any())
            ->method('compute')
            ->will($this->returnValue($result));


        $worker->process($message);
    }

    public function testProcessFailed()
    {
        $job = $this->getJob(Filesystem::create($this->target));
        $this->assertTrue($job->isOk());

        $message = $this->getAMQPMessage($job->toJson());
        $conn = $this->getConnection();
        $channel = $this->getChannel();

        $conn->expects($this->once())
            ->method('channel')
            ->will($this->returnValue($channel));

        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);
        $this->probePublishValues($channel, false, Configuration::ROUTINGKEY_LOG);

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
        $job = $this->getWrongJob(Filesystem::create($this->target));
        $this->assertTrue($job->isOk());

        $json = $job->toJson();
        $data = json_decode($json, true);
        $data['type'] = 'Gloubster\\Message\\Job\\ImageJob';
        $json = json_encode($data);

        $message = $this->getAMQPMessage($json);
        $channel = $this->getChannel();

        $this->ensureAcknowledgement($channel, $message->delivery_info['delivery_tag']);
        $this->probePublishValues($channel, false, Configuration::ROUTINGKEY_LOG);

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
        $worker->setTimeout(2);
        $worker->run();

        $this->assertGreaterThanOrEqual(2, count($collector));

        foreach($collector as $message) {
            $job = MessageFactory::fromJson($message->body);
            $this->assertInstanceOf('Gloubster\\Message\\Presence\\WorkerPresence', $job);
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

                    if(MessageFactory::fromJson($message->body) instanceof WorkerPresence) {
                        return;
                    }

                    $that->assertEquals(Configuration::EXCHANGE_DISPATCHER, $exchangeName);
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

    public function assertGoodLogJob(AMQPMessage $message)
    {
        $job = MessageFactory::fromJson($message->body);

        $this->assertFalse($job->isOnError());
        $this->assertGreaterThan(0, $job->getBeginning());
        $this->assertGreaterThan(0, $job->getEnd());
        $this->assertGreaterThan($job->getBeginning(), $job->getEnd());
        $this->assertGreaterThan(0, $job->getDeliveryDuration());
        $this->assertGreaterThan(0, $job->getProcessDuration());

        $this->assertArrayHasKey('type', $job->getResult());
        $this->assertArrayHasKey('sha1', $job->getResult());
        $this->assertArrayHasKey('sha256', $job->getResult());
        $this->assertArrayHasKey('md5', $job->getResult());

        $this->assertInstanceOf('Gloubster\\Delivery\\DeliveryInterface', $job->getDelivery());
        $this->assertEquals($this->getId(), $job->getWorkerId());
    }

    public function assertGoodLogWrongJob(AMQPMessage $message)
    {
        $job = MessageFactory::fromJson($message->body);

        $this->assertInstanceOf('Gloubster\\Message\\Job\\JobInterface', $job);

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

class TestReceipt extends AbstractReceipt
{
    protected $path;

    public function setPath($value)
    {
        $this->path = $value;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getName()
    {
        return 'test';
    }

    public function acknowledge(JobInterface $job)
    {
        file_put_contents($this->path, $job->toJson());
    }
}
