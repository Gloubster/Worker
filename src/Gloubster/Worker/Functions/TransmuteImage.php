<?php

namespace Gloubster\Worker\Functions;

use Gloubster\Communication\Query;
use Gloubster\Communication\Result;
use MediaAlchemyst\Specification\Image;
use MediaAlchemyst\Exception\Exception;

class TransmuteImage extends AbstractFunction
{

    public function getFunctionName()
    {
        return Query::FUNCTION_TRANSMUTE_IMAGE;
    }

    protected function processQuery(\GearmanJob $job, Query $query)
    {
        $start = microtime(true);

        $job->sendStatus(0, 100);

        $tempfile = tempnam(sys_get_temp_dir(), 'transmute_image');
        $tempdest = tempnam(sys_get_temp_dir(), 'transmute_image');

        if (false === $filecontent = @file_get_contents($query->getFile())) {
            $this->logger->addInfo(sprintf('Unable to download file `%s`', $query->getFile()));

            return new Result($job->handle(), $query->getUuid(), $job->workload(), null, (microtime(true) - $start), array(), array(sprintf('Unable to download file `%s`', $query->getFile())));
        }

        $this->logger->addInfo(sprintf('file %s retrieved', $query->getFile()));
        $job->sendStatus(30, 100);

        file_put_contents($tempfile, $filecontent);
        unset($filecontent);

        $job->sendStatus(50, 100);
        $specification = new Image();

        $width = $height = null;

        foreach ($query->getParameters() as $name => $value) {
            switch ($name) {
                case 'width':
                    $width = $value;
                    break;
                case 'height':
                    $height = $value;
                    break;
                case 'quality':
                    $specification->setQuality($value);
                    break;
            }
        }

        if (null !== $width && null !== $height) {
            $specification->setDimensions($width, $height);
        }

        try {
            $this->alchemyst->open($tempfile)
                ->turnInto($tempdest, $specification)
                ->close();
        } catch (Exception $e) {
            $this->logger->addInfo(sprintf('A media-alchemyst exception occured %s', $e->getMessage()));

            return new Result($job->handle(), $query->getUuid(), $job->workload(), null, (microtime(true) - $start), array(), array(sprintf('A media-alchemyst exception occured %s', $e->getMessage())));
        }

        $result = new Result($job->handle(), $query->getUuid(), $job->workload(), file_get_contents($tempdest), (microtime(true) - $start));

        unlink($tempfile);
        unlink($tempdest);

        $this->logger->addInfo('Conversion successfull');
        $job->sendStatus(100, 100);

        return $result;
    }
}

