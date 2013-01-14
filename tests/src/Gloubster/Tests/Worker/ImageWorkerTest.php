<?php

namespace Gloubster\Tests\Worker;

use Gloubster\Worker\ImageWorker;
use Gloubster\Message\Job\ImageJob;
use Gloubster\Delivery\Filesystem;
use Gloubster\Message\Factory as MessageFactory;
use Gloubster\Worker\Job\Result;
use Gloubster\Delivery\DeliveryInterface;
use Neutron\TemporaryFilesystem\TemporaryFilesystem;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

require_once __DIR__ . '/AbstractWorkerTest.php';

/**
 * @covers Gloubster\Worker\AbstractWorker
 * @covers Gloubster\Worker\ImageWorker
 */
class ImageWorkerTest extends AbstractWorkerTest
{
    const ID = 'jolie.id';
    const QUEUE = 'jolie.queue';

    private $source;

    public function __construct()
    {
        $this->source = __DIR__ . '/../../../testfiles/photo02.JPG';
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

        return $this->getMock('Gloubster\Worker\ImageWorker', array('sendPresence'), array(self::ID, $conn, self::QUEUE, new TemporaryFilesystem, $logger));
    }

    /**
     * @covers Gloubster\Worker\ImageWorker::compute
     */
    public function testCompute()
    {
        $target = tempnam(sys_get_temp_dir(), 'compute-image');
        $data = $this->getWorker()->compute($this->getJob(Filesystem::create($target)));

        $this->assertInstanceOf('Gloubster\Worker\Job\Result', $data);

        $this->assertTrue(file_exists($data->getData()));
        $this->assertEquals(Result::TYPE_PATHFILE, $data->getType());

        if (file_exists($target)) {
            unlink($target);
        }
    }

    public function assertGoodLocalLogJob(AMQPMessage $message)
    {
        $job = MessageFactory::fromJson($message->body);

        $this->assertEquals(array('format' => 'jpg'), $job->getParameters());

        $this->assertArrayHasKey('raw_image', $job->getResult());
        $this->assertArrayHasKey('multiple_layers', $job->getResult());
        $this->assertArrayHasKey('width', $job->getResult());
        $this->assertArrayHasKey('height', $job->getResult());
        $this->assertArrayHasKey('channels', $job->getResult());
        $this->assertArrayHasKey('focal_length', $job->getResult());
        $this->assertArrayHasKey('color_depth', $job->getResult());
        $this->assertArrayHasKey('camera_model', $job->getResult());
        $this->assertArrayHasKey('flash_fired', $job->getResult());
        $this->assertArrayHasKey('aperture', $job->getResult());
        $this->assertArrayHasKey('shutter_speed', $job->getResult());
        $this->assertArrayHasKey('orientation', $job->getResult());
        $this->assertArrayHasKey('creation_date', $job->getResult());
        $this->assertArrayHasKey('hyperfocal_distance', $job->getResult());
        $this->assertArrayHasKey('ISO', $job->getResult());
        $this->assertArrayHasKey('light_value', $job->getResult());
        $this->assertArrayHasKey('color_space', $job->getResult());
    }

    public function assertWrongLocalLogJob(AMQPMessage $message)
    {
        $job = MessageFactory::fromJson($message->body);

        $this->assertEquals(array('format' => 'jpg'), $job->getParameters());
        $this->assertTrue($job->isOnError());
    }

    public function getJob(DeliveryInterface $delivery)
    {
        return ImageJob::create($this->source, $delivery, array('format' => 'jpg'));
    }

    public function getWrongJob(DeliveryInterface $delivery)
    {
        $job = new FakeSpace\NonImageJob();
        $job->setDelivery($delivery);
        $job->setParameters(array('format' => 'jpg'));

        return $job;
    }

    public function getId()
    {
        return self::ID;
    }

    public function getQueueName()
    {
        return self::QUEUE;
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
        $job = ImageJob::create(__DIR__ . '/../../../testfiles/photo02.JPG', Filesystem::create($this->target), $parameters);
        $message = $this->getAMQPMessage($job->toJson());

        $this->getWorker()->process($message);

        $this->assertTrue(file_exists($this->target));
    }
}
