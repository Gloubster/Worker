<?php

/*
 * This file is part of Gloubster.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gloubster\Worker\Job;

class Counter implements \Countable
{
    private $total = 0;
    private $success = 0;
    private $lastUpdate;
    private $startedOn;

    public function __construct()
    {
        $this->total = $this->success = 0;
        $this->startedOn = microtime(true);
    }

    public function count()
    {
        return $this->getTotal();
    }

    public function getStartTimestamp()
    {
        return $this->startedOn;
    }

    public function getUpdateTimestamp()
    {
        return $this->lastUpdate;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function getSuccess()
    {
        return $this->success;
    }

    public function getFailures()
    {
        return $this->total - $this->success;
    }

    public function add($quantity = 1)
    {
        $this->total += $quantity > 0 ? (int) $quantity : 0;
        $this->lastUpdate = microtime(true);
    }

    public function addSuccess($quantity = 1)
    {
        $this->success += $quantity > 0 ? (int) $quantity : 0;
        $this->lastUpdate = microtime(true);
    }
}
