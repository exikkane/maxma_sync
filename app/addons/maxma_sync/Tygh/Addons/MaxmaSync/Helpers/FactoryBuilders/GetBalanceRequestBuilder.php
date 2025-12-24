<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class GetBalanceRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::GET_BALANCE;
    }

    public function build(array $payload): Model\ClientQuery
    {
        if (!isset($payload['phoneNumber'])) {
            throw new \InvalidArgumentException('Payload for GET_BALANCE must contain "phoneNumber" key.');
        }
        return (new Model\ClientQuery())
            ->setPhoneNumber($payload['phoneNumber'])
            ->setExternalId((string)$payload['externalId'] ?? '');
    }
}