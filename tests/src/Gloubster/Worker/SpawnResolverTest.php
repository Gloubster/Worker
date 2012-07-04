<?php

namespace Gloubster\Worker;

class SpawnResolverTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers Gloubster\Worker\SpawnResolver::__construct
     * @covers Gloubster\Worker\SpawnResolver::getSpawnQuantity
     */
    public function testGetSpawnQuantity()
    {
        $cpuInfos = $this->getMock('\\Neutron\\System\\CpuInfo', array('getTotalCores'));
        $cpuInfos->expects($this->any())
            ->method('getTotalCores')
            ->will($this->returnValue(14));

        $configuration = new Configuration(file_get_contents(__DIR__ . '/../../ressources/good-configurations/conf1.json'));

        $object = new SpawnResolver($configuration, $cpuInfos);

        $this->assertEquals(14, $object->getSpawnQuantity());

        $cpuInfos = $this->getMock('\\Neutron\\System\\CpuInfo', array('getTotalCores'));
        $cpuInfos->expects($this->any())
            ->method('getTotalCores')
            ->will($this->returnValue(12));

        $configuration = new Configuration(file_get_contents(__DIR__ . '/../../ressources/good-configurations/conf2.json'));

        $object = new SpawnResolver($configuration, $cpuInfos);

        $this->assertEquals(1, $object->getSpawnQuantity());



        $cpuInfos = $this->getMock('\\Neutron\\System\\CpuInfo', array('getTotalCores'));
        $cpuInfos->expects($this->any())
            ->method('getTotalCores')
            ->will($this->returnValue(32));

        $configuration = new Configuration(file_get_contents(__DIR__ . '/../../ressources/good-configurations/conf3.json'));

        $object = new SpawnResolver($configuration, $cpuInfos);

        $this->assertEquals(25, $object->getSpawnQuantity());
    }

    /**
     * @covers Gloubster\Worker\SpawnResolver::getClassName
     */
    public function testGetClassName()
    {
        $cpuInfos = $this->getMock('\\Neutron\\System\\CpuInfo');
        $configuration = new Configuration(file_get_contents(__DIR__ . '/../../ressources/good-configurations/conf1.json'));
        $object = new SpawnResolver($configuration, $cpuInfos);

        $this->assertEquals('\\Gloubster\\Worker\\Functions\\TransmuteImage', $object->getClassName());
    }
}
