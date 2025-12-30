<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
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
        $client = new ClientDto(
            $payload['phoneNumber'],
        );
        if (empty($client->getPhoneNumber())) {
            throw new \InvalidArgumentException('Payload for GET_BALANCE must contain "phoneNumber" key.');
        }
        return (new Model\ClientQuery())
            ->setPhoneNumber($client->getPhoneNumber());
    }
}