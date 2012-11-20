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

use Gloubster\Exception\InvalidArgumentException;

class Result
{
    const TYPE_PATHFILE = 'pathfile';
    const TYPE_BINARYSTRING = 'binary';

    private $type;
    private $data;

    public function __construct($type, $data)
    {
        if (!in_array($type, array(self::TYPE_BINARYSTRING, self::TYPE_PATHFILE))) {
            throw new InvalidArgumentException(sprintf('Type %s is not a valid type', $type));
        }

        if (!is_scalar($data)) {
            throw new InvalidArgumentException('Data must be scalar');
        }

        $this->type = $type;
        $this->data = $data;
    }

    public function isPath()
    {
        return self::TYPE_PATHFILE === $this->type;
    }

    public function isBinary()
    {
        return self::TYPE_BINARYSTRING === $this->type;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getType()
    {
        return $this->type;
    }
}
