<?php

namespace Gloubster\Worker;

use Gloubster\Job\JobInterface;

class TestWorker extends AbstractWorker
{
    public static $iterations;
    
    public function compute(JobInterface $job)
    {

    }

    public function run($iterations = true)
    {
        self::$iterations = $iterations;
    }

    public function getType()
    {
        return 'test';
    }
}

