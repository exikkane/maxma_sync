<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ClientDto
{
    public function __construct(
        private readonly string $phoneNumber,
        private readonly string $externalId
    ) {}

    /**
     * @return string
     */
    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    /**
     * @return string
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * Сериализация в массив
     */
    public function toArray(): array
    {
        return [
            'phoneNumber' => $this->phoneNumber,
            'externalId'  => $this->externalId,
        ];
    }
}
