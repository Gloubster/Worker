<?php

namespace Gloubster\Gearman;

use Monolog\Logger;

class Worker
{
    protected $worker;
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->worker = new \GearmanWorker();
        $this->logger = $logger;
        $this->logger->addInfo('Gearman Worker starting');
    }

    public function addServer($host, $port)
    {
        $this->worker->addServer($host, $port);
        $this->logger->addInfo(sprintf('Add server %s:%s', $host, $port));
    }

    public function addFunction($functionName, $callback, &$context = null, $timeout = 0)
    {
        $this->worker->addFunction($functionName, $callback, &$context, $timeout);
        $this->logger->addInfo(sprintf('Register function %s', $functionName));
    }

    public function run()
    {
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
    }

    /**
     * Ping all gearman servers and return true if all of them are online.
     * Return false if at least one is offline.
     *
     * @return Boolean
     */
    public function ping()
    {
        return @$this->worker->echo('Hello There');
    }
}
