<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
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
        $client = new ClientDto(
            $payload['phoneNumber']
        );
        if (empty($client->getPhoneNumber())) {
            throw new \InvalidArgumentException('Payload for GET_BONUS_HISTORY must contain "phoneNumber" key.');
        }
        $clientObj = (new Model\ClientQuery())
            ->setPhoneNumber($client->getPhoneNumber());

        return (new Model\GetBonusHistoryRequest())
            ->setClient($clientObj);
    }
}