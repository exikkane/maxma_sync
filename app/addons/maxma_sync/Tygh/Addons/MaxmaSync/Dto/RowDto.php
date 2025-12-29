<?php

namespace Tygh\Addons\MaxmaSync\Dto;


final class RowDto
{
    public function __construct(
        public string $id,
        public float $qty,
        public ProductDto $product
    ) {}

    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'qty'     => $this->qty,
            'product' => $this->product->toArray(),
        ];
    }
}
