<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class BalanceDto
{
    public function __construct(
        private readonly int $balance = 0,
        public  readonly int $pending_bonuses = 0,
        private readonly int $updated_at = 0
    ) {}

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

    public function toArray(): array
    {
        return [
            'balance'         => $this->balance,
            'pending_bonuses' => $this->pending_bonuses,
            'updated_at'      => $this->updated_at,
        ];
    }
}