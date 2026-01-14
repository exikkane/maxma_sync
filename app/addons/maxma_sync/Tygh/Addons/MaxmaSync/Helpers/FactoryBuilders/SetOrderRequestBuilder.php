<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class SetOrderRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::SET_ORDER;
    }

    public function build(array $payload): Model\V2SetOrderRequest
    {
        $calc_query = $payload['calculationQuery'];
        if (!isset($calc_query)) {
            throw new \InvalidArgumentException('Payload for SET_ORDER must contain "calculationQuery" key.');
        }

        $request = new Model\V2SetOrderRequest();

        if (!empty($calc_query['client']['phoneNumber'])) {
            $clientObj = (new Model\ClientQuery())
                ->setPhoneNumber((string)$calc_query['client']['phoneNumber']);
        }

        $shopObj = (new Model\ShopQuery())
            ->setCode($calc_query['shop']['code'] ?? 'CS-Cart')
            ->setName($calc_query['shop']['name'] ?? 'CS-Cart');
        $rows = [];


        foreach ($calc_query['rows'] as $item) {
            $product = $item['product'];
            $productObj = (new Model\Product())
                ->setExternalId((string)$product['externalId'])
                ->setSku((string)$product['sku'])
                ->setTitle((string)$product['title'])
                ->setBlackPrice((float)($product['blackPrice'] ?? 0));

            if ($product['redPrice']) {
                $productObj->setRedPrice((float)$product['redPrice']);
            }

            $row = (new Model\CalculationQueryRow())
                ->setId((string)$item['id'])
                ->setQty((float)$item['qty'])
                ->setProduct($productObj);

            $rows[] = $row;
        }

        $calculationQueryObj = (new Model\CalculationQuery())
            ->setClient($clientObj)
            ->setShop($shopObj)
            ->setRows($rows)
            ->setApplyBonuses($calc_query['applyBonuses'])
            ->setCollectBonuses($calc_query['collectBonuses']);

        if (!empty($calc_query['promocode'])) {
            $calculationQueryObj->setPromocode($calc_query['promocode']);
        }

        if (isset($payload['orderId'])) {
            $request->setOrderId($payload['orderId']);
        }

        return $request->setCalculationQuery($calculationQueryObj);
    }
}