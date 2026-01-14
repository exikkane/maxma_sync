<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class CalculationQueryDto
{
    private ClientDto $client;
    private ShopDto $shop;
    private string $promocode;
    private int $applyBonuses;
    private int $collectBonuses;
    private array $rows;
    /**
     * @param RowDto[] $rows
     */
    public function __construct(
        ClientDto $client,
        ShopDto $shop,
        string $promocode,
        int $applyBonuses,
        int $collectBonuses,
        array $rows
    ) {
        $this->client = $client;
        $this->shop = $shop;
        $this->promocode = $promocode;
        $this->applyBonuses = $applyBonuses;
        $this->collectBonuses = $collectBonuses;
        $this->rows = $rows;
    }


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