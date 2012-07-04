<?php

namespace Gloubster\Worker\Functions;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Gloubster\Configuration;
use Gloubster\Delivery\Factory;
use Gloubster\Communication\Query;

class TransmuteImageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TransmuteImage
     */
    protected $object;
    protected $configuration;
    protected $factory;

    /**
     * @covers Gloubster\Worker\Functions\TransmuteImage::__construct
     */
    protected function setUp()
    {
        $dir = tempnam(sys_get_temp_dir(), 'transmuteimage');
        unlink($dir);
        mkdir($dir);

        $conf = array(
            'gearman-servers' => array(
                array(
                    'host'     => 'localhost',
                    'port'     => '4730',
                )
            ),
            'delivery' => array(
                'name'          => 'FilesystemStore',
                'configuration' => array(
                    'path' => $dir
                )
            )
        );

        $this->configuration = new Configuration(json_encode($conf));
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $this->factory = new Factory();

        $this->object = new TransmuteImage($this->configuration, $logger, $this->factory);
    }

    /**
     * @covers Gloubster\Worker\Functions\TransmuteImage::getFunctionName
     */
    public function testGetFunctionName()
    {
        $this->assertInternalType('string', $this->object->getFunctionName());
    }

    /**
     * @covers Gloubster\Worker\Functions\TransmuteImage::processQuery
     * @covers Gloubster\Worker\Functions\TransmuteImage::execute
     */
    public function testExecute()
    {
        $uuid = mt_rand();
        $handle = 'job-' . mt_rand(10, 100);
        $file = 'file://' . __DIR__ . '/../../../testfiles/photo02.JPG';

        $delivery = $this->factory->build($this->configuration['delivery']['name'], $this->configuration['delivery']['configuration']);

        $dimensions = 100;
        $query = new Query($uuid, $file, $delivery->getName(), $delivery->getSignature(), array('quality'=>50, 'width'  => $dimensions, 'height' => $dimensions));

        $job = $this->getGearmanJobMock($query, $handle, $uuid);

        $this->object->execute($job);

        $result = $delivery->retrieve($uuid);
        $this->assertInstanceOf('\\Gloubster\\Communication\\Result', $result);

        $file = tempnam(sys_get_temp_dir(), 'resultcheck') . '.jpg';
        file_put_contents($file, $result->getBinaryData());
        $infos = getimagesize($file);

        $this->assertLessThanOrEqual($dimensions, $infos[0]);
        $this->assertLessThanOrEqual($dimensions, $infos[1]);
        $this->assertEquals($dimensions, max($infos[0], $infos[1]));

        unlink($file);
    }

    /**
     * @covers Gloubster\Worker\Functions\TransmuteImage::processQuery
     */
    public function testExecuteWrongFileType()
    {
        $uuid = mt_rand();
        $handle = 'job-' . mt_rand(10, 100);
        $file = 'file://' . __FILE__;

        $delivery = $this->factory->build($this->configuration['delivery']['name'], $this->configuration['delivery']['configuration']);

        $query = new Query($uuid, $file, $delivery->getName(), $delivery->getSignature());

        $job = $this->getGearmanJobMock($query, $handle, $uuid);

        $this->object->execute($job);

        $result = $delivery->retrieve($uuid);
        $this->assertInstanceOf('\\Gloubster\\Communication\\Result', $result);
        $this->assertGreaterThanOrEqual(1, $result->getErrors());
    }

    /**
     * @covers Gloubster\Worker\Functions\TransmuteImage::processQuery
     */
    public function testExecuteNonExistentFile()
    {
        $uuid = mt_rand();
        $handle = 'job-' . mt_rand(10, 100);
        $file = 'file://' . __FILE__ . mt_rand();

        $delivery = $this->factory->build($this->configuration['delivery']['name'], $this->configuration['delivery']['configuration']);

        $query = new Query($uuid, $file, $delivery->getName(), $delivery->getSignature());

        $job = $this->getGearmanJobMock($query, $handle, $uuid);

        $this->object->execute($job);

        $result = $delivery->retrieve($uuid);
        $this->assertInstanceOf('\\Gloubster\\Communication\\Result', $result);
        $this->assertGreaterThanOrEqual(1, $result->getErrors());
    }

    protected function getGearmanJobMock($query, $handle, $uuid)
    {
        $job = $this->getMock('\\GearmanJob', array('workload', 'handle', 'unique', 'sendStatus'));

        $job->expects($this->any())
            ->method('workload')
            ->will($this->returnValue(serialize($query)));

        $job->expects($this->any())
            ->method('handle')
            ->will($this->returnValue($handle));

        $job->expects($this->any())
            ->method('unique')
            ->will($this->returnValue($uuid));

        return $job;
    }
}

