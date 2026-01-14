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
    private $items;

    /** @var int */
    private $pagination;

    /** @var int */
    private $updatedAt;

    /**
     * @param array<int, array{date: string, amount: int, operation: string, operation_name: string}> $items
     * @param int $pagination
     * @param int $updatedAt
     */
    public function __construct(array $items, int $pagination, int $updatedAt)
    {
        $this->items = $items;
        $this->pagination = $pagination;
        $this->updatedAt = $updatedAt;
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

    // Геттеры для доступа к свойствам (если нужно)
    public function getItems(): array
    {
        return $this->items;
    }

    public function getPagination(): int
    {
        return $this->pagination;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }
}
