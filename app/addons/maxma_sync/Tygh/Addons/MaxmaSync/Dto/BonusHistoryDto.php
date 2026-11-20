<?php

namespace Tygh\Addons\MaxmaSync\Dto;

use CloudLoyalty\Api\Generated\Model\GetBonusHistoryResponse;

final class BonusHistoryDto
{
    /**
     * @var array<int, array{
     *     date: string,
     *     amount: int,
     *     operation: string,
     *     operation_name: string
     * }>
     */
    /** @var list<array{date: string, amount: int, operation: string, operation_name: string}> */
    public array $items;

    /** @param list<array{date: string, amount: int, operation: string, operation_name: string}> $items */
    public function __construct(
        array $items,
        public int $pagination,
        public int $updatedAt
    ) {
        $this->items = $items;
    }

    public static function fromApiResponse(GetBonusHistoryResponse $response): self
    {
        $items = [];

        foreach ($response->getHistory() ?? [] as $entry) {
            $items[] = [
                'date'           => $entry->getAt()->format('Y-m-d H:i:s'),
                'amount'         => (int) $entry->getAmount(),
                'operation'      => $entry->getOperation(),
                'operation_name' => $entry->getOperationName(),
            ];
        }

        return new self(
            $items,
            (int) ($response->getPagination()?->getTotal() ?? count($items)),
            time()
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $result = $this->items;
        $result['pagination'] = $this->pagination;
        $result['updated_at'] = $this->updatedAt;

        return $result;
    }
}
