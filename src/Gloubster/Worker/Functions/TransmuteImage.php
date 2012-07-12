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
        $start = $interstart = microtime(true);

        $timers = array();

        $job->sendStatus(0, 100);

        $tempfile = tempnam(sys_get_temp_dir(), 'transmute_image');
        $tempdest = tempnam(sys_get_temp_dir(), 'transmute_image') . '.jpg';

        if (false === $filecontent = $this->getFile($query->getFile())) {
            $this->logger->addInfo(sprintf('Unable to download file `%s`', $query->getFile()));

            return array(new Result($job->handle(), $query->getUuid(), $job->workload(), $this->workerName, $start, microtime(true), array(), array(sprintf('Unable to download file `%s`', $query->getFile()))), null);
        }

        $timers[] = microtime(true) - $interstart;
        $interstart = microtime(true);

        $this->logger->addInfo(sprintf('file %s retrieved', $query->getFile()));
        $job->sendStatus(30, 100);

        file_put_contents($tempfile, $filecontent);
        unset($filecontent);

        $timers[] = microtime(true) - $interstart;
        $interstart = microtime(true);

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

            $timers[] = microtime(true) - $interstart;
            $interstart = microtime(true);
        } catch (Exception $e) {
            $this->logger->addInfo(sprintf('A media-alchemyst exception occured %s', $e->getMessage()));

            return array(new Result($job->handle(), $query->getUuid(), $job->workload(), $this->workerName, $start, microtime(true), array(), array(sprintf('A media-alchemyst exception occured %s', $e->getMessage()))), null);
        } catch (\Exception $e) {
            $this->logger->addInfo(sprintf('An unexpected exception occured %s', $e->getMessage()));

            return array(new Result($job->handle(), $query->getUuid(), $job->workload(), $this->workerName, $start, microtime(true), array(), array(sprintf('An unexpected exception occured %s', $e->getMessage()))), null);
        }

        $datas = file_get_contents($tempdest);

        $timers[] = microtime(true) - $interstart;
        $interstart = microtime(true);

        unlink($tempfile);
        unlink($tempdest);

        $timers[] = microtime(true) - $interstart;

        $this->logger->addInfo('Conversion successfull');
        $job->sendStatus(100, 100);

        $result = new Result($job->handle(), $query->getUuid(), $job->workload(), $this->workerName, $start, microtime(true));
        $result->setTimers($timers);

        return array($result, $datas);
    }
}

