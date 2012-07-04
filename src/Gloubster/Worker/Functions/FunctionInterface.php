<?php

namespace Gloubster\Worker\Functions;

interface FunctionInterface
{
    public function getFunctionName();
    public function execute(\GearmanJob $job);
}
