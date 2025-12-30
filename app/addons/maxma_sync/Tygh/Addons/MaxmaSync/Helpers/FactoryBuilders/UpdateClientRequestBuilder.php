<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
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

        $client = new ClientDto(
            $payload['client']['phoneNumber'],
        );
        $clientUpdateDto = new ClientUpdateDto(
            $payload['client']['user_id'],
            $payload['client']['email'],
            $payload['client']['phoneNumber'],
            $payload['client']['name'],
            $payload['client']['surname'],
            $payload['shop']['code'],
            $payload['shop']['name'],
        );

        $clientUpdateObj = (new Model\ClientInfoQuery())
            ->setEmail($clientUpdateDto->getEmail())
            ->setPhoneNumber($clientUpdateDto->getPhoneNumber())
            ->setName($clientUpdateDto->getName())
            ->setSurname($clientUpdateDto->getSurname())
            ->setFullName(trim($clientUpdateDto->getName() . ' ' . $clientUpdateDto->getSurname()));

        return (new Model\UpdateClientRequest())
            ->setPhoneNumber($client->getPhoneNumber())
            ->setClient($clientUpdateObj);
    }
}