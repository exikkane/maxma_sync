<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class UpdateClientRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::UPDATE_CLIENT;
    }

    public function build(array $payload): Model\UpdateClientRequest
    {
        if (!isset($payload['client'])) {
            throw new \InvalidArgumentException('Payload for UPDATE_CLIENT must contain "client" key.');
        }

        $c = $payload['client'];
        $clientObj = (new Model\ClientInfoQuery())
            ->setEmail($c['email'] ?? '')
            ->setPhoneNumber($c['phoneNumber'] ?? '')
            ->setName($c['name'] ?? '')
            ->setSurname($c['surname'] ?? '')
            ->setFullName($c['name'] . ' ' . $c['surname'] ?? '')
            ->setExternalId((string) $c['user_id'] ?? '');

        return (new Model\UpdateClientRequest())
            ->setPhoneNumber($c['phoneNumber'] ?? '')
            ->setExternalId((string) $c['user_id'] ?? '')
            ->setClient($clientObj);
    }
}