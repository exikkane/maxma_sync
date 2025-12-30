<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ReturnItemDto
{
    public function __construct(
        private readonly string $sku,
        private readonly int $itemCount,
        private readonly int $price
    ) {}
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
