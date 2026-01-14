<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ReturnItemDto
{
    private string $sku;
    private int $itemCount;
    private int $price;
    public function __construct(
        string $sku,
        int $itemCount,
        int $price
    ) {
        $this->sku = $sku;
        $this->itemCount = $itemCount;
        $this->price = $price;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }

    public function getPrice(): int
    {
        return $this->price;
    }
    public function toArray(): array
    {
        return [
            'sku'       => $this->sku,
            'itemCount' => $this->itemCount,
            'price'     => $this->price,
        ];
    }
}
