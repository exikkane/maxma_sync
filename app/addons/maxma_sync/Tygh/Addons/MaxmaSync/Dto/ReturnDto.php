<?php

namespace Tygh\Addons\MaxmaSync\Dto;

use DateTime;

final class ReturnDto
{
    /**
     * @param ReturnItemDto[] $items
     */
    public function __construct(
        private readonly int $id,
        private readonly DateTime $executedAt,
        private readonly int $purchaseId,
        private readonly int $refundAmount,
        private readonly string $shopCode,
        private readonly string $shopName,
        private readonly array $items = []
    ) {}

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getExecutedAt(): DateTime
    {
        return $this->executedAt;
    }

    public function getPurchaseId(): string
    {
        return (string) $this->purchaseId;
    }

    public function getRefundAmount(): int
    {
        return $this->refundAmount;
    }

    public function getShopCode(): string
    {
        return $this->shopCode;
    }

    public function getShopName(): string
    {
        return $this->shopName;
    }

    /**
     * @return ReturnItemDto[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'executedAt'   => $this->executedAt,
            'purchaseId'   => $this->purchaseId,
            'refundAmount' => $this->refundAmount,
            'shopCode'     => $this->shopCode,
            'shopName'     => $this->shopName,
            'items'        => $this->items,
        ];
    }
}
