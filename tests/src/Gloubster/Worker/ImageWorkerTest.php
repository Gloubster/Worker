<?php

namespace Gloubster\Worker;

use Gloubster\Job\ImageJob;
use Gloubster\Job\VideoJob;
use Gloubster\Delivery\FileSystem;
use Gloubster\Delivery\DeliveryInterface;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/AbstractWorkerTest.php';

/**
 * @covers Gloubster\Worker\ImageWorker
 */
class ImageWorkerTest extends AbstractWorkerTest
{
    const ID = 'jolie.id';
    const QUEUE = 'jolie.queue';
    const LOG_EXCHANGE = 'jolie.logs';

    private $source;

    public function __construct()
    {
        $this->source = __DIR__ . '/../../testfiles/photo02.JPG';
    }

    /**
     * @return \Gloubster\Worker\ImageWorker
     */
    public function getWorker(AMQPChannel $channel = null)
    {
        $logger = $this->getMockBuilder('Monolog\\Logger')
            ->disableOriginalConstructor()
            ->getMock();

        if (!$channel) {
            $channel = $this->getMockBuilder('PhpAmqpLib\Channel\AMQPChannel')
                ->disableOriginalConstructor()
                ->getMock();
        }

        $conn = $this->getMockBuilder('PhpAmqpLib\Connection\AMQPConnection')
            ->disableOriginalConstructor()
            ->getmock();

        $conn->expects($this->any())
            ->method('channel')
            ->will($this->returnValue($channel));

        return $this->getMock('Gloubster\Worker\ImageWorker', array('sendPresence'), array(self::ID, $conn, self::QUEUE, self::LOG_EXCHANGE, new TemporaryFilesystem, $logger));
    }

    /**
     * @covers Gloubster\Worker\ImageWorker::compute
     */
    public function testCompute()
    {
        $target = tempnam(sys_get_temp_dir(), 'compute-image');
        $data = $this->getWorker()->compute($this->getJob(new FileSystem($target)));

        $this->assertTrue(file_exists($data));

        if (file_exists($target)) {
            unlink($target);
        }
    }

    public function assertGoodLocalLogJob(AMQPMessage $message)
    {
        $job = unserialize($message->body);

        $this->assertEquals(array('format' => 'jpg'), $job->getParameters());
        $this->assertEquals($this->source, $job->getSource());
    }

    public function assertWrongLocalLogJob(AMQPMessage $message)
    {
        $job = unserialize($message->body);

        $this->assertEquals(array('format' => 'jpg'), $job->getParameters());
        $this->assertEquals($this->source, $job->getSource());
        $this->assertTrue($job->isOnError());
    }

    public function getJob(DeliveryInterface $delivery)
    {
        return new ImageJob($this->source, $delivery, array('format' => 'jpg'));
    }

    public function getWrongJob(DeliveryInterface $delivery)
    {
        return new VideoJob($this->source, $delivery, array('format' => 'jpg'));
    }

    public function getId()
    {
        return self::ID;
    }

    public function getQueueName()
    {
        return self::QUEUE;
    }

    public function getLogExchangeName()
    {
        return self::LOG_EXCHANGE;
    }

    public function testWithOptions()
    {
        $parameters = array(
            'width'            => 320,
            'height'           => 240,
            'format'           => 'gif',
            'resize-mode'      => ImageJob::RESIZE_INBOUND,
            'resolution-units' => ImageJob::RESOLUTION_PER_CENTIMETERS,
            'resolution'       => 200,
            'strip'            => true,
            'rotation'         => 90,
            'quality'          => 100,
        );
        $job = new ImageJob(__DIR__ . '/../../testfiles/photo02.JPG', new FileSystem($this->target), $parameters);
        $message = $this->getAMQPMessage(serialize($job));

        $this->getWorker()->process($message);

        $this->assertTrue(file_exists($this->target));
    }
}
