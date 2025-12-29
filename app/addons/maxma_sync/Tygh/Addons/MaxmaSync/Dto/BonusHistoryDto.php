<?php

namespace Tygh\Addons\MaxmaSync\Dto;

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
    public array $items;

    public function __construct(
        array $items,
        public int $pagination,
        public int $updatedAt
    ) {
        $this->items = $items;
    }

    public static function fromApiResponse(object $response): self
    {
        $items = [];

        foreach ($response->getHistory() ?? [] as $entry) {
            $items[] = [
                'date'           => $entry->getAt()->format('Y-m-d H:i:s'),
                'amount'         => (int) $entry->getAmount(),
                'operation'      => (string) $entry->getOperation(),
                'operation_name' => (string) $entry->getOperationName(),
            ];
        }

        return new self(
            $items,
            (int) $response->getPagination()->getTotal(),
            time()
        );
    }

    public function toArray(): array
    {
        $result = $this->items;
        $result['pagination'] = $this->pagination;
        $result['updated_at'] = $this->updatedAt;

        return $result;
    }
}