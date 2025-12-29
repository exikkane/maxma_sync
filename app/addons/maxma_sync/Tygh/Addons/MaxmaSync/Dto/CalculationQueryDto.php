<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class CalculationQueryDto
{
    /**
     * @param RowDto[] $rows
     */
    public function __construct(
        public ClientDto $client,
        public ShopDto $shop,
        public string $promocode,
        public int $applyBonuses,
        public int $collectBonuses,
        public array $rows
    ) {}

    public function toArray(): array
    {
        $rows = [];

        foreach ($this->rows as $row) {
            $rows[] = $row->toArray();
        }

        return [
            'client'          => $this->client->toArray(),
            'shop'            => $this->shop->toArray(),
            'promocode'       => $this->promocode,
            'applyBonuses'    => $this->applyBonuses,
            'collectBonuses'  => $this->collectBonuses,
            'rows'            => $rows,
        ];
    }
}