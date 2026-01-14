<?php

namespace Tygh\Addons\MaxmaSync\Dto;

use DateTime;

final class ReturnDto
{
    private int $id;
    private DateTime $executedAt;
    private int $purchaseId;
    private int $refundAmount;
    private string $shopCode;
    private string $shopName;
    private array $items;
    /**
     * @param ReturnItemDto[] $items
     */
    public function __construct(
        int $id,
        DateTime $executedAt,
        int $purchaseId,
        int $refundAmount,
        string $shopCode,
        string $shopName,
        array $items = []
    ) {
        $this->id = $id;
        $this->executedAt = $executedAt;
        $this->purchaseId = $purchaseId;
        $this->refundAmount = $refundAmount;
        $this->shopCode = $shopCode;
        $this->shopName = $shopName;
        $this->items = $items;
    }


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
