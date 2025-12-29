<?php

namespace Tygh\Addons\MaxmaSync\Dto;


final class ClientDto
{
    public function __construct(
        public string $phoneNumber,
        public string $externalId
    ) {}

    public function toArray(): array
    {
        return [
            'phoneNumber' => $this->phoneNumber,
            'externalId'  => $this->externalId,
        ];
    }
}