<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ShopDto
{
    public function __construct(
        private readonly string $code,
        private readonly string $name
    ) {}

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Сериализация в массив
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
