<?php

namespace Gloubster\Delivery;

use Gloubster\Delivery\DeliveryInterface;

class DeliveryBinary implements DeliveryInterface
{
    private $id = 'binary-id';

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function fetch($id)
    {
        ;
    }

    public function getName()
    {
        return 'delivery-binary';
    }

    public function deliverBinary($data)
    {

    }

    public function deliverFile($pathfile)
    {

    }

    public static function fromArray(array $data)
    {
        return new static($data['id']);
    }

    public function toArray()
    {
        return array(
            'id'   => $this->id,
            'name' => $this->getName()
        );
    }
}
