<?php

namespace Gloubster\Gearman\Functions;

use Monolog\Logger;

class TransmuteImage implements FunctionInterface
{
    protected $alchemyst;
    protected $logger;
    protected $redis;

    public function __construct(Logger $logger, Redis $redis)
    {
        $drivers = new \MediaAlchemyst\DriversContainer(
                new \Symfony\Component\DependencyInjection\ParameterBag\ParameterBag(array()),
                $logger
        );
        $this->logger = $logger;
        $this->redis = $redis;
        $this->alchemyst = new \MediaAlchemyst\Alchemyst($drivers);
    }

    public function getFunctionName()
    {
        return 'transmute_image';
    }

    public function execute(\GearmanJob $job)
    {
        $this->logger->addInfo(sprintf('Receiving job handle %s (%s)', $job->handle(), $job->unique()));

        $job->sendStatus(0, 100);
        $datas = json_decode($job->workload(), true);

        if (null === $datas) {
            $this->logger->addInfo(sprintf('Unable to unserialize datas %s', $job->workload()));
        }

        $tempfile = tempnam(sys_get_temp_dir(), 'transmute_image');
        $tempdest = tempnam(sys_get_temp_dir(), 'transmute_image');

        $job->sendStatus(30, 100);
        if (false === $filecontent = @file_get_contents($datas['file'])) {
            $this->logger->addInfo(sprintf('Unable to download file `%s`', $datas['file']));

            return;
        } else {
            $this->logger->addInfo(sprintf('file %s retrieved', $datas['file']));
        }

        $job->sendStatus(50, 100);

        file_put_contents($tempfile, $filecontent);

        $job->sendStatus(80, 100);
        $specification = new \MediaAlchemyst\Specification\Image();

        try {
            $this->alchemyst->open($tempfile)
                ->turnInto($tempdest, $specification)
                ->close();
        } catch (\MediaAlchemyst\Exception\Exception $e) {
            $this->logger->addInfo(sprintf('A media-alchemyst exception occured %s', $e->getMessage()));
            return;
        }

        $job->sendStatus(100, 100);

        $this->logger->addInfo('Conversion successfull');

        unlink($tempfile);
        unlink($tempdest);
    }
}

