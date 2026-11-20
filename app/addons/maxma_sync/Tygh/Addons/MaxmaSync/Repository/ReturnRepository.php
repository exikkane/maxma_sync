<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace Tygh\Addons\MaxmaSync\Repository;

use Tygh\Enum\Addons\Rma\ReturnOperationStatuses;

class ReturnRepository
{
    /**
     * Fetch approved items in one query, with the unit price saved by RMA.
     *
     * @return list<array{return_id: int|string, timestamp: int|string, product_id: int|string, sku: string|null, amount: int|string, price: float|string}>
     */
    public function getApprovedItems(int $order_id): array
    {
        /** @var list<array{return_id: int|string, timestamp: int|string, product_id: int|string, sku: string|null, amount: int|string, price: float|string}> $items */
        $items = db_get_array(
            "SELECT r.return_id, r.timestamp, i.product_id, COALESCE(NULLIF(d.product_code, ''), p.product_code) AS sku, i.amount, i.price"
            . ' FROM ?:rma_returns AS r'
            . ' INNER JOIN ?:rma_return_products AS i ON i.return_id = r.return_id'
            . ' LEFT JOIN ?:order_details AS d ON d.order_id = r.order_id AND d.item_id = i.item_id'
            . ' LEFT JOIN ?:products AS p ON p.product_id = i.product_id'
            . ' WHERE r.order_id = ?i AND r.status IN (?a) AND i.type = ?s AND i.amount > 0'
            . ' ORDER BY r.return_id, i.item_id',
            $order_id,
            [ReturnOperationStatuses::APPROVED, ReturnOperationStatuses::COMPLETED],
            ReturnOperationStatuses::APPROVED
        );
        return $items;
    }
}
