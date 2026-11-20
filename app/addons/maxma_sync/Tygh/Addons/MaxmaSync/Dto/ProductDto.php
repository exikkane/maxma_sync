<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ProductDto
{
    public function __construct(
        private readonly string $externalId,
        private readonly string $sku,
        private readonly string $title,
        private readonly float $blackPrice,
        private readonly ?float $redPrice
    ) {}

    /**
     * @return string
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return float
     */
    public function getBlackPrice(): float
    {
        return $this->blackPrice;
    }

    /**
     * @return float|null
     */
    public function getRedPrice(): ?float
    {
        return $this->redPrice;
    }

    /**
     * Сериализация в массив
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'externalId'  => $this->externalId,
            'sku'         => $this->sku,
            'title'       => $this->title,
            'blackPrice'  => $this->blackPrice,
        ];

        if ($this->redPrice !== null) {
            $result['redPrice'] = $this->redPrice;
        }
        return $result;
    }
}
