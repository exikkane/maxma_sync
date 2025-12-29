<?php

namespace Tygh\Addons\MaxmaSync\Dto;


final class ProductDto
{
    public function __construct(
        public string $externalId,
        public string $sku,
        public string $title,
        public float $buyingPrice,
        public float $blackPrice,
        public float $redPrice
    ) {}

    public function toArray(): array
    {
        return [
            'externalId'  => $this->externalId,
            'sku'         => $this->sku,
            'title'       => $this->title,
            'buyingPrice' => $this->buyingPrice,
            'blackPrice'  => $this->blackPrice,
            'redPrice'    => $this->redPrice,
        ];
    }
}