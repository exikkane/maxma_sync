<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class ConfirmOrderRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::CONFIRM_ORDER;
    }

    public function build(array $payload): Model\ConfirmOrderRequest
    {
        if (!isset($payload['orderId'])) {
            throw new \InvalidArgumentException(
                'Payload for CONFIRM_ORDER must contain "orderId"'
            );
        }

        return (new Model\ConfirmOrderRequest())
            ->setOrderId($payload['orderId']);
    }
}