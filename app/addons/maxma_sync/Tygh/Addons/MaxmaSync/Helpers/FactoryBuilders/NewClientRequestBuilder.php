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

        $clientUpdateDto = new ClientUpdateDto(
            $payload['client']['user_id'],
            $payload['client']['email'],
            $payload['client']['phoneNumber'],
            $payload['client']['name'],
            $payload['client']['surname'],
            $payload['shop']['code'],
            $payload['shop']['name'],
        );

        $clientObj = (new Model\ClientInfoQuery())
            ->setEmail($clientUpdateDto->getEmail())
            ->setPhoneNumber($clientUpdateDto->getPhoneNumber())
            ->setName($clientUpdateDto->getName())
            ->setSurname($clientUpdateDto->getSurname())
            ->setFullName(trim($clientUpdateDto->getName() . ' ' . $clientUpdateDto->getSurname()));

        $shop = new ShopDto(
            $clientUpdateDto->getShopCode(),
            $clientUpdateDto->getShopName(),
        );

        $shopObj = (new Model\ShopQuery())
            ->setCode($shop->getCode())
            ->setName($shop->getName());

        return (new Model\NewClientRequest())
            ->setClient($clientObj)
            ->setShop($shopObj);
    }
}