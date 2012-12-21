<?php

/*
 * This file is part of Gloubster.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gloubster\Worker\FakeSpace;

use Gloubster\Delivery\DeliveryInterface;
use Gloubster\Exception\InvalidArgumentException;
use Gloubster\Receipt\ReceiptInterface;

class NonImageJob
{

    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function toJson()
    {
        return json_encode(array_merge(array(
            'name' => $this->getName(),
            'type' => get_class($this),
        ), $this->getArrayData()));
    }

    /**
     * {@inheritdoc}
     */
    public static function fromJson($json)
    {
        return Factory::fromJson($json);
    }


    /**
     * {@inheritdoc}
     */
    public function isOk($throwException = false)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorMessage($message)
    {
        $this->errorMessage = $message;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function getDelivery()
    {
        return $this->delivery;
    }

    public function setDelivery(DeliveryInterface $delivery = null)
    {
        $this->delivery = $delivery;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setError($boolean)
    {
        $this->error = (Boolean) $boolean;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isOnError()
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getBeginning()
    {
        return null === $this->beginning ? $this->beginning : (float) $this->beginning;
    }

    public function setBeginning($beginning)
    {
        $this->beginning = (string) $beginning;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnd($microtime)
    {
        $this->end = (string) $microtime;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnd()
    {
        return null === $this->end ? $this->end : (float) $this->end;
    }

    /**
     * {@inheritdoc}
     */
    public function setProcessDuration($duration)
    {
        $this->processDuration = $duration;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessDuration()
    {
        return $this->processDuration;
    }

    /**
     * {@inheritdoc}
     */
    public function setDeliveryDuration($duration)
    {
        $this->deliveryDuration = $duration;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeliveryDuration()
    {
        return $this->deliveryDuration;
    }

    /**
     * {@inheritdoc}
     */
    public function setWorkerId($id)
    {
        $this->workerId = $id;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     * {@inheritdoc}
     */
    public function requireReceipt()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setReceipts(array $receipts)
    {
        array_map(function ($receipt) {
                if (! $receipt instanceof ReceiptInterface) {
                    throw new InvalidArgumentException('setReceipts only accept ReceiptInterface');
                }
            }, $receipts
        );

        $this->receipts = $receipts;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getReceipts()
    {
        return $this->receipts;
    }

    /**
     * {@inheritdoc}
     */
    public function pushReceipt(ReceiptInterface $receipt)
    {
        array_push($this->receipts, $receipt);

        return $this;
    }

    public static function create($source, DeliveryInterface $delivery, array $parameters = array())
    {
        $job = new static();

        return $job->setSource($source)
            ->setDelivery($delivery)
            ->setParameters($parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getArrayData()
    {
        $data = array();

        foreach ($this as $key => $parameter) {
            if ($key === 'delivery' && null !== $parameter) {
                $data[$key] = $parameter->toArray();
            } elseif ($key === 'receipts' && null !== $parameter) {
                $data[$key] = array();
                foreach ($parameter as $receipt) {
                    $data[$key][] = $receipt->toArray();
                }
            } else {
                $data[$key] = $parameter;
            }
        }

        return $data;
    }
    public function getName()
    {
        return 'non-image';
    }

    /**
     * {@inheritdoc}
     */
    public function getMandatoryParameters()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutingKey()
    {
        return RabbitMQConfiguration::ROUTINGKEY_IMAGE_PROCESSING;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchangeName()
    {
        return RabbitMQConfiguration::EXCHANGE_DISPATCHER;
    }
}
