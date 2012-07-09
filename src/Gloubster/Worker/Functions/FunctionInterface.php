<?php

namespace Gloubster\Worker\Functions;

interface FunctionInterface
{
    public function getFunctionName();
    public function setWorkerName($name);
    public function execute(\GearmanJob $job);
}
