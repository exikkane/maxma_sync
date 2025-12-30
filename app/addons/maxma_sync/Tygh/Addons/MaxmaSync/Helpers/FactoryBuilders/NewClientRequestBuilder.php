<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
use Tygh\Addons\MaxmaSync\Dto\ShopDto;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class NewClientRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::NEW_CLIENT;
    }

    public function build(array $payload): Model\NewClientRequest
    {
        if (!isset($payload['client'])) {
            throw new \InvalidArgumentException('Payload for NEW_CLIENT must contain "client" key.');
        }

        $clientDto = ClientUpdateDto::fromArray((int)($payload['client']['user_id'] ?? 0), $payload['client']);

        $clientObj = (new Model\ClientInfoQuery())
            ->setEmail($clientDto->getEmail())
            ->setPhoneNumber($clientDto->getPhoneNumber())
            ->setName($clientDto->getName())
            ->setSurname($clientDto->getSurname())
            ->setFullName(trim($clientDto->getName() . ' ' . $clientDto->getSurname()))
            ->setExternalId((string) $clientDto->getUserId());

        $shop = new ShopDto(
            $payload['calculationQuery']['shop']['code'],
            $payload['calculationQuery']['shop']['name']
        );

        $shopObj = (new Model\ShopQuery())
            ->setCode($shop->getCode())
            ->setName($shop->getName());

        return (new Model\NewClientRequest())
            ->setClient($clientObj)
            ->setShop($shopObj);
    }
}