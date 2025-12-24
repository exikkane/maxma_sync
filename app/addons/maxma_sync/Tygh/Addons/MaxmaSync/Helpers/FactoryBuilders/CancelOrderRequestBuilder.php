<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class CancelOrderRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::CANCEL_ORDER;
    }

    public function build(array $payload): Model\CancelOrderRequest
    {
        if (!isset($payload['orderId'])) {
            throw new \InvalidArgumentException(
                'Payload for CANCEL_ORDER must contain "orderId"'
            );
        }

        return (new Model\CancelOrderRequest())
            ->setOrderId($payload['orderId']);
    }
}