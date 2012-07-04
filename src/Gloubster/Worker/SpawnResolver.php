<?php

namespace Gloubster\Worker;

use Neutron\System\CpuInfo;

/**
 * Reads configuration file and system core configuration,
 * resolves the spawn distribution
 */
class SpawnResolver
{
    protected $configuration;
    protected $cpuInfo;

    public function __construct(Configuration $configuration, CpuInfo $cpuInfo)
    {
        $this->configuration = $configuration;
        $this->cpuInfo = $cpuInfo;
    }

    public function getSpawnQuantity()
    {
        $spawns = 1;

        $availablethreads = $this->cpuInfo->getTotalCores();

        $spec = $this->configuration['worker']['specification'];

        if ( ! isset($spec['quantity']) || strtolower($spec['quantity']) == 'auto') {
            $threads = isset($spec['threads']) ? $spec['threads'] : 1;
            $spawns = max(1, floor($availablethreads / $threads));
        } elseif (isset($spec['quantity'])) {
            $spawns = (int) $spec['quantity'];
        }

        return $spawns;
    }

    public function getClassName()
    {
        $name = $this->configuration['worker']['specification']['name'];

        switch ($name) {
            case 'image':
                $classname = '\\Gloubster\\Worker\\Functions\\TransmuteImage';
                break;
            default:
                throw new \Gloubster\Exception\InvalidArgumentException(sprintf('Specification type `%s` is not yet handled', $name));
                break;
        }

        return $classname;
    }
}

