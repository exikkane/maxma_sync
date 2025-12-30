<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ClientUpdateDto
{
    private int $userId;
    private string $email;
    private string $phoneNumber;
    private string $name;
    private string $surname;
    private string $shopCode;
    private string $shopName;

    public function __construct(
        int $userId,
        string $email = '',
        string $phoneNumber = '',
        string $name = '',
        string $surname = '',
        string $shopCode = '',
        string $shopName = ''
    ) {
        $this->userId = $userId;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->name = $name;
        $this->surname = $surname;
        $this->shopCode = $shopCode;
        $this->shopName = $shopName;
    }

    public static function fromArray(int $userId, array $userData, array $shopData = []): self
    {
        return new self(
            $userId,
            $userData['email'] ?? '',
            $userData['phone'] ?? '',
            $userData['firstname'] ?? '',
            $userData['lastname'] ?? '',
            $shopData['shopCode'] ?? '',
            $shopData['shopName'] ?? ''
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
            'shop' => [
                'code'        => $this->shopCode,
                'name'        => $this->shopName,
            ]
        ];
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getShopCode(): string
    {
        return $this->shopCode;
    }

    public function getShopName(): string
    {
        return $this->shopName;
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
