<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ClientUpdateDto
{
    public int $userId;
    public string $email;
    public string $phoneNumber;
    public string $name;
    public string $surname;

    public function __construct(
        int $userId,
        string $email = '',
        string $phoneNumber = '',
        string $name = '',
        string $surname = ''
    ) {
        $this->userId = $userId;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->name = $name;
        $this->surname = $surname;
    }

    public static function fromArray(int $userId, array $userData): self
    {
        return new self(
            $userId,
            $userData['email'] ?? '',
            $userData['phone'] ?? '',
            $userData['firstname'] ?? '',
            $userData['lastname'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'client' => [
                'user_id'     => $this->userId,
                'email'       => $this->email,
                'phoneNumber' => $this->phoneNumber,
                'name'        => $this->name,
                'surname'     => $this->surname,
            ],
        ];
    }
}