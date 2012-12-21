<?php

/*
 * This file is part of Gloubster.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gloubster\Worker;

use MediaAlchemyst\Alchemyst;
use MediaAlchemyst\DriversContainer;
use MediaAlchemyst\Specification\Image;
use Gloubster\Exception\RuntimeException;
use Gloubster\Message\Job\ImageJob;
use Gloubster\Message\Job\JobInterface;
use Gloubster\Worker\Job\Result;

class ImageWorker extends AbstractWorker
{

    public function getType()
    {
        return 'Image';
    }

    public function compute(JobInterface $job)
    {
        if (!$job instanceof ImageJob) {
            throw new RuntimeException('Image worker only process image job');
        }

        // throws a RuntimeException in case a parameter is missing
        $job->isOk(true);

        $parameters = $job->getParameters();
        $tmpFile = $this->filesystem->createEmptyFile(sys_get_temp_dir(), null, null, $parameters['format'], 50);

        $alchemyst = new Alchemyst(new DriversContainer());
        $alchemyst->open($job->getSource())
            ->turnInto($tmpFile, $this->specificationsFromJob($job))
            ->close();

        return new Result(Result::TYPE_PATHFILE, $tmpFile);
    }

    private function specificationsFromJob(ImageJob $job)
    {
        $specifications = new Image();

        $parameters = $this->populateParameters($job->getParameters(), array(
            'width'            => null,
            'height'           => null,
            'quality'          => null,
            'resize-mode'      => null,
            'resolution'       => null,
            'resolution-units' => null,
            'resolution-x'     => null,
            'resolution-y'     => null,
            'rotation'         => null,
            'strip'            => null,
        ));

        if ($parameters['width'] && $parameters['height']) {
            $specifications->setDimensions($parameters['width'], $parameters['height']);
        }

        if ($parameters['quality']) {
            $specifications->setQuality($parameters['quality']);
        }

        if ($parameters['resize-mode']) {
            switch ($parameters['resize-mode']) {
                case ImageJob::RESIZE_OUTBOUND:
                    $mode = Image::RESIZE_MODE_OUTBOUND;
                    break;
                case ImageJob::RESIZE_INBOUND:
                    $mode = Image::RESIZE_MODE_INBOUND;
                    break;
                default:
                case ImageJob::RESIZE_INBOUND_FIXEDRATIO:
                    $mode = Image::RESIZE_MODE_INBOUND_FIXEDRATIO;
                    break;
            }

            $specifications->setResizeMode($mode);
        }

        if ($parameters['resolution'] || ($parameters['resolution-x'] && $parameters['resolution-y'])) {

            $units = Image::RESOLUTION_PIXELPERINCH;
            if ($parameters['resolution-units'] === ImageJob::RESOLUTION_PER_CENTIMETERS) {
                $units = Image::RESOLUTION_PIXELPERCENTIMETER;
            }
            if ($parameters['resolution']) {
                $res_x = $res_y = $parameters['resolution'];
            } else {
                $res_x = $parameters['resolution-x'];
                $res_y = $parameters['resolution-y'];
            }

            $specifications->setResolution($res_x, $res_y, $units);
        }

        if ($parameters['rotation']) {
            $specifications->setRotationAngle($parameters['rotation']);
        }

        if ($parameters['strip']) {
            $specifications->setStrip($parameters['strip']);
        }

        return $specifications;
    }

    private function populateParameters(array $parameters, array $default)
    {
        foreach ($default as $key => $value) {
            if (!isset($parameters[$key])) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }
}
