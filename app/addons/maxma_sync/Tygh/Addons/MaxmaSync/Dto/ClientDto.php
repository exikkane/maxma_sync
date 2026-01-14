<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ClientDto
{
    private string $phoneNumber;
    private string $externalId;
    public function __construct(
        string $phoneNumber,
        string $externalId = ''
    ) {
        $this->phoneNumber = $phoneNumber;
        $this->externalId = $externalId;
    }


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
        $result = [
            'phoneNumber' => $this->phoneNumber
        ];

        if ($this->externalId !== '') {
            $result['externalId'] = $this->externalId;
        }

        return $result;
    }
}
