<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
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

        $c = $payload['client'];
        $clientObj = (new Model\ClientInfoQuery())
            ->setEmail($c['email'] ?? '')
            ->setPhoneNumber($c['phoneNumber'] ?? '')
            ->setName($c['name'] ?? '')
            ->setSurname($c['surname'] ?? '')
            ->setFullName($c['name'] . ' ' . $c['surname'] ?? '')
            ->setExternalId((string) $c['user_id'] ?? '');

        $shopObj = (new Model\ShopQuery())
            ->setCode($payload['calculationQuery']['shop']['code'] ?? 'CS-Cart')
            ->setName($payload['calculationQuery']['shop']['name'] ?? 'CS-Cart');

        return (new Model\NewClientRequest())
            ->setClient($clientObj)
            ->setShop($shopObj);
    }
}