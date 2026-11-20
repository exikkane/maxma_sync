<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace Tygh\Addons\MaxmaSync\Service;

use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Repository\ReturnRepository;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class ReturnService
{
    public function __construct(
        private readonly ReturnRepository $returns,
        private readonly QueueRepository $queue
    ) {
    }

    /**
     * Schedule one MAXMA transaction per approved RMA.
     *
     * @param array<string, mixed> $settings
     */
    public function enqueueApprovedReturns(int $order_id, array $settings): void
    {
        $lock = 'returns:' . $order_id;
        if (!$this->queue->acquireLock($lock)) {
            throw new \RuntimeException('Could not obtain the MAXMA return queue lock.');
        }

        try {
            $payloads = [];
            foreach ($this->returns->getApprovedItems($order_id) as $item) {
                $id = (int) $item['return_id'];
                $payloads[$id] ??= [
                    'id' => $id,
                    'executedAt' => (int) $item['timestamp'],
                    'shopCode' => $settings['maxma_shop_code'] ?? '',
                    'shopName' => $settings['maxma_shop_name'] ?? '',
                    'purchaseId' => $order_id,
                    'items' => [],
                    'refundAmount' => 0.0,
                ];
                $amount = (int) $item['amount'];
                $payloads[$id]['items'][] = [
                    'sku' => $item['sku'] !== null && $item['sku'] !== '' ? $item['sku'] : (string) $item['product_id'],
                    'itemCount' => $amount,
                ];
                $payloads[$id]['refundAmount'] += (float) $item['price'] * $amount;
            }

            foreach ($payloads as $id => $payload) {
                if (!$this->queue->hasReturn($id, $order_id)) {
                    $payload['refundAmount'] = fn_format_price($payload['refundAmount']);
                    $this->queue->add(RequestTypes::APPLY_RETURN, $id, $payload);
                }
            }
        } finally {
            $this->queue->releaseLock($lock);
        }
    }
}
