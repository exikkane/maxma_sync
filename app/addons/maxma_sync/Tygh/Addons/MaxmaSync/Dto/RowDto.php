<?php

namespace Tygh\Addons\MaxmaSync\Dto;


final class RowDto
{
    private string $id;
    private float $qty;
    private ProductDto $product;
    public function __construct(
        string $id,
        float $qty,
        ProductDto $product
    ) {
        $this->id = $id;
        $this->qty = $qty;
        $this->product = $product;
    }


    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'qty'     => $this->qty,
            'product' => $this->product->toArray(),
        ];
    }
}
