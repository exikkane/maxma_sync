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

    public function build(array $payload): Model\ApplyReturnRequest
    {
        $return = new ReturnDto(
            $payload['id'],
            (new DateTime())->setTimestamp($payload['executedAt']),
            $payload['purchaseId'],
            $payload['refundAmount'],
            $payload['shopCode'] ?? 'CS-Cart',
            $payload['shopName'] ?? 'CS-Cart',
            $payload['items']
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
                ->setItemCount((int) $item['itemCount']);

        }
        $transaction->setItems($transaction_items);

        return $request->setTransaction($transaction);
    }
}