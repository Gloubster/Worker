<?php

namespace Gloubster\Gearman\Functions;

use Gloubster\Communication\Result;
use MediaAlchemyst\Specification\Image;
use MediaAlchemyst\Exception\Exception;
use Gloubster\Configuration;
use Gloubster\Delivery\Factory;
use MediaAlchemyst\Alchemyst;
use MediaAlchemyst\DriversContainer;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class TransmuteImage extends AbstractFunction
{
    public function getFunctionName()
    {
        return 'transmute_image';
    }

    protected function processQuery(\GearmanJob $job, \Gloubster\Communication\Query $query)
    {
        $start = microtime(true);

        $job->sendStatus(0, 100);

        $tempfile = tempnam(sys_get_temp_dir(), 'transmute_image');
        $tempdest = tempnam(sys_get_temp_dir(), 'transmute_image');

        if (false === $filecontent = @file_get_contents($query->getFile())) {
            $this->logger->addInfo(sprintf('Unable to download file `%s`', $query->getFile()));

            return;
        }

        $this->logger->addInfo(sprintf('file %s retrieved', $query->getFile()));
        $job->sendStatus(30, 100);

        file_put_contents($tempfile, $filecontent);
        unset($filecontent);

        $job->sendStatus(50, 100);
        $specification = new Image();

        try {
            $this->alchemyst->open($tempfile)
                ->turnInto($tempdest, $specification)
                ->close();
        } catch (Exception $e) {
            $this->logger->addInfo(sprintf('A media-alchemyst exception occured %s', $e->getMessage()));

            return;
        }

        $result = new Result($job->jobHandle(), $query->getUuid(), $job->workload(), file_get_contents($tempdest), (microtime(true) - $start));

        unlink($tempfile);
        unlink($tempdest);

        $this->logger->addInfo('Conversion successfull');
        $job->sendStatus(100, 100);

        return $result;
    }
}

