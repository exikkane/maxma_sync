<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ShopDto
{
    public function __construct(
        public string $code,
        public string $name
    ) {}

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}