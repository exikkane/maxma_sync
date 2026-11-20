<?php

namespace Tygh\Addons\MaxmaSync\Dto;

use CloudLoyalty\Api\Generated\Model\GetBalanceResponse;

final class BalanceDto
{
    public function __construct(
        private readonly int $balance = 0,
        private readonly int $pending_bonuses = 0,
        private readonly int $updated_at = 0
    ) {}

    /**
     * @return int
     */
    public function getBalance(): int
    {
        return $this->balance;
    }

    /**
     * @return int
     */
    public function getPendingBonuses(): int
    {
        return $this->pending_bonuses;
    }

    /**
     * @return int
     */
    public function getUpdatedAt(): int
    {
        return $this->updated_at;
    }

    /**
     * Создание DTO из ответа API
     */
    public static function fromApiResponse(GetBalanceResponse $response): ?self
    {
        $client_info = $response->getClient();
        $balance = $client_info?->getBonuses();

        if ($client_info === null || $balance === null) {
            return null;
        }

        return new self(
            $balance,
            $client_info->getPendingBonuses() ?? 0,
            time()
        );
    }

    /**
     * Сериализация в массив
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return [
            'balance'         => $this->balance,
            'pending_bonuses' => $this->pending_bonuses,
            'updated_at'      => $this->updated_at,
        ];
    }
}
