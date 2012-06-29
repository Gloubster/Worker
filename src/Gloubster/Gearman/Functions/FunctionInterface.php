<?php

namespace Gloubster\Gearman\Functions;

use Monolog\logger;

interface FunctionInterface
{
    public function getFunctionName();
    public function execute(\GearmanJob $job);
}
