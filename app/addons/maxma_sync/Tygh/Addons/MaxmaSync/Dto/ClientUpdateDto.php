<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ClientUpdateDto
{
    private int $userId;
    private string $email;
    private string $phoneNumber;
    private string $name;
    private string $surname;

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

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }
}
