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
            $payload['phoneNumber'],
            $payload['externalId'],
        );
        $clientUpdateDto = ClientUpdateDto::fromArray((int)($payload['client']['user_id'] ?? 0), $payload['client']);

        $clientUpdateObj = (new Model\ClientInfoQuery())
            ->setEmail($clientUpdateDto->getEmail())
            ->setPhoneNumber($clientUpdateDto->getPhoneNumber())
            ->setName($clientUpdateDto->getName())
            ->setSurname($clientUpdateDto->getSurname())
            ->setFullName(trim($clientUpdateDto->getName() . ' ' . $clientUpdateDto->getSurname()))
            ->setExternalId((string) $clientUpdateDto->getUserId());

        return (new Model\UpdateClientRequest())
            ->setPhoneNumber($client->getPhoneNumber())
            ->setExternalId($client->getExternalId())
            ->setClient($clientUpdateObj);
    }
}