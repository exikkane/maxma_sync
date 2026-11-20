<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use DateTime;
use Tygh\Addons\MaxmaSync\Dto\ReturnDto;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class ApplyReturnRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::APPLY_RETURN;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public function build(array $payload): Model\ApplyReturnRequest
    {
        if (!isset($payload['id'], $payload['executedAt'], $payload['purchaseId'], $payload['refundAmount'], $payload['items'])
            || !is_numeric($payload['refundAmount'])
            || (float) $payload['refundAmount'] < 0
            || !is_array($payload['items'])
            || !$payload['items']
        ) {
            throw new \InvalidArgumentException('Invalid MAXMA return transaction.');
        }
        $items = [];
        foreach ($payload['items'] as $item) {
            if (!is_array($item) || !is_string($item['sku'] ?? null) || $item['sku'] === '') {
                throw new \InvalidArgumentException('Invalid MAXMA return item SKU.');
            }
            $count = $item['itemCount'] ?? null;
            if ((!is_int($count) && !(is_string($count) && ctype_digit($count))) || (int) $count <= 0) {
                throw new \InvalidArgumentException('Invalid MAXMA return item quantity.');
            }
            $items[] = ['sku' => $item['sku'], 'itemCount' => (int) $count];
        }

        $return = new ReturnDto(
            $payload['id'],
            (new DateTime())->setTimestamp($payload['executedAt']),
            $payload['purchaseId'],
            (float) $payload['refundAmount'],
            $payload['shopCode'] ?? 'CS-Cart',
            $payload['shopName'] ?? 'CS-Cart',
            $items
        );

        $request = new Model\ApplyReturnRequest();

        $transaction = (new Model\ApplyReturnRequestTransaction())
            ->setId($return->getId())
            ->setExecutedAt($return->getExecutedAt())
            ->setPurchaseId($return->getPurchaseId())
            ->setRefundAmount($return->getRefundAmount())
            ->setShopCode($return->getShopCode())
            ->setShopName($return->getShopName());

        $transaction_items = [];
        foreach ($return->getItems() as $item) {
            $transaction_items[] = (new Model\ApplyReturnRequestTransactionItemsItem())
                ->setSku($item['sku'])
                ->setItemCount($item['itemCount']);

        }
        $transaction->setItems($transaction_items);

        return $request->setTransaction($transaction);
    }
}
