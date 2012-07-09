<?php

namespace Gloubster\Worker;

use Monolog\Logger;

class Worker
{
    protected $name;
    protected $worker;
    protected $logger;

    public function __construct($name, \GearmanWorker $worker, Logger $logger)
    {
        $this->name = $name;
        $this->worker = $worker;
        $this->logger = $logger;
        $this->logger->addInfo('Gearman Worker starting');
    }

    public function getName()
    {
        return $this->name;
    }

    public function addServer($host, $port)
    {
        $this->worker->addServer($host, $port);
        $this->logger->addDebug(sprintf('Add server %s:%s', $host, $port));
    }

    public function setFunction(Functions\FunctionInterface $function)
    {
        $function->setWorkerName($this->getName());
        $this->worker->addFunction($function->getFunctionName(), array($function, 'execute'));
        $this->logger->addDebug(sprintf('Register function %s', $function->getFunctionName()));
    }

    public function run()
    {
        $this->logger->addInfo('Start running');

        while (true) {
            $this->worker->work();
            switch ($this->worker->returnCode()) {
                case GEARMAN_SUCCESS:
                    $this->logger->addInfo('Job finished');
                    break;
                case GEARMAN_NO_REGISTERED_FUNCTIONS:
                    $this->logger->addError('No functions have been registered');
                    break;
                default:
                    $this->logger->addError(sprintf('An error occured : `%s`', $this->worker->error()));
                    break;
            }
        }

        $this->logger->addInfo('Stop running');
    }

    /**
     * Ping all gearman servers and return true if all of them are online.
     * Return false if at least one is offline.
     *
     * @return Boolean
     */
    public function ping()
    {
        return $this->worker->echo('Hello There');
    }
}
