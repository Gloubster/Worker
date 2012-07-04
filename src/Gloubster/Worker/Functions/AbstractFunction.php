<?php

namespace Gloubster\Worker\Functions;

use Gloubster\Communication\Query;
use Gloubster\Configuration;
use Gloubster\Delivery\Factory;
use Gloubster\Exception\RuntimeException;
use MediaAlchemyst\Alchemyst;
use MediaAlchemyst\DriversContainer;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

abstract class AbstractFunction implements FunctionInterface
{
    protected $alchemyst;
    protected $logger;
    protected $deliveryFactory;
    protected $configuration;

    final public function __construct(Configuration $configuration, Logger $logger, Factory $factory)
    {
        $this->configuration = $configuration;
        $this->deliveryFactory = $factory;

        $spec = $configuration['worker']['specification'];

        $drivers = new DriversContainer(new ParameterBag(array(
                    'ffmpeg.threads' => isset($spec['threads']) ? $spec['threads'] : 1
                )), $logger);

        $this->logger = $logger;
        $this->alchemyst = new Alchemyst($drivers);
    }

    final public function execute(\GearmanJob $job)
    {
        $this->logger->addInfo(sprintf('Receiving job handle %s (%s)', $job->handle(), $job->unique()));

        try {
            $query = unserialize($job->workload());

            if ( ! $query instanceof Query) {
                throw new RuntimeException('Expecting a Gloubster Query');
            }
        } catch (RuntimeException $e) {
            $this->logger->addError(sprintf('Error while getting the job : %s', $e->getMessage()));

            return;
        }

        try {
            $query->getDelivery($this->deliveryFactory, $this->configuration['delivery']['configuration'])
                ->deliver($query->getUuid(), $this->processQuery($job, $query));
        } catch (Exception $e) {
            $this->logger->addError(sprintf('Error while processing : %s', $e->getMessage()));
        }
    }

    abstract protected function processQuery(\GearmanJob $job, Query $query);
}

