<?php

namespace Tygh\Addons\MaxmaSync\Dto;

final class ProductDto
{
    private string $externalId;
    private string $sku;
    private string $title;
    private float $blackPrice;
    private float $redPrice;
    public function __construct(
        string $externalId,
        string $sku,
        string $title,
        float $blackPrice,
        float $redPrice
    ) {
        $this->externalId = $externalId;
        $this->sku = $sku;
        $this->title = $title;
        $this->blackPrice = $blackPrice;
        $this->redPrice = $redPrice;
    }


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
     * @return float
     */
    public function getRedPrice(): float
    {
        return $this->redPrice;
    }

    /**
     * Сериализация в массив
     */
    public function toArray(): array
    {
        $result = [
            'externalId'  => $this->externalId,
            'sku'         => $this->sku,
            'title'       => $this->title,
            'blackPrice'  => $this->blackPrice,
        ];

        if (!empty($this->redPrice)) {
            $result['redPrice'] = $this->redPrice;
        }
        return $result;
    }
}
