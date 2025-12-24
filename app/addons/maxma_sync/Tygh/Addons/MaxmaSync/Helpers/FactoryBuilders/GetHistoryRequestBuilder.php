<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class GetHistoryRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::GET_BONUS_HISTORY;
    }

    public function build(array $payload): Model\GetBonusHistoryRequest
    {
        if (!isset($payload['phoneNumber'])) {
            throw new \InvalidArgumentException('Payload for GET_BONUS_HISTORY must contain "phoneNumber" key.');
        }
        $clientObj = (new Model\ClientQuery())
            ->setPhoneNumber($payload['phoneNumber'])
            ->setExternalId((string) $payload['externalId'] ?? '');

        return (new Model\GetBonusHistoryRequest())
            ->setClient($clientObj);
    }
}