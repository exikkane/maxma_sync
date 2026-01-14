<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ShopDto
{
    private string $code;
    private string $name;
    public function __construct(
        string $code,
        string $name
    ) {
        $this->code = $code;
        $this->name = $name;
    }


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
