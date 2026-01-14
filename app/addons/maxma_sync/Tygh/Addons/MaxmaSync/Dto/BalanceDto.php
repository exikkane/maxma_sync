<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class BalanceDto
{
    private int $balance;
    private int $pending_bonuses;
    private int $updated_at;
    public function __construct(
        int $balance = 0,
        int $pending_bonuses = 0,
        int $updated_at = 0
    ) {
        $this->balance = $balance;
        $this->pending_bonuses = $pending_bonuses;
        $this->updated_at = $updated_at;
    }


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
    public static function fromApiResponse(object $response): self
    {
        $client_info = $response->getClient();

        if (empty($client_info)) {
            return new self();
        }

        return new self(
            (int) $client_info->getBonuses(),
            (int) $client_info->getPendingBonuses(),
            time()
        );
    }

    /**
     * Сериализация в массив
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
