<?php

namespace Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
use Tygh\Addons\MaxmaSync\Dto\ProductDto;
use Tygh\Addons\MaxmaSync\Dto\ShopDto;
use Tygh\Addons\MaxmaSync\Interfaces\RequestBuilderInterface;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

final class CalculatePurchaseRequestBuilder implements RequestBuilderInterface
{
    public function supports(string $method): bool
    {
        return $method === RequestTypes::CALCULATE_PURCHASE;
    }

    public function build(array $payload): Model\V2CalculatePurchaseRequest
    {
        $calc_query = $payload['calculationQuery'];
        if (!isset($calc_query)) {
            throw new \InvalidArgumentException('Payload for CALCULATE_PURCHASE must contain "calculationQuery" key.');
        }

        $request = new Model\V2CalculatePurchaseRequest();

        $client = new ClientDto(
            $calc_query['client']['phoneNumber'],
        );

        $clientObj = (new Model\ClientQuery())
            ->setPhoneNumber($client->getPhoneNumber());

        $shop = new ShopDto(
            $calc_query['shop']['code'],
            $calc_query['shop']['name']
        );

        $shopObj = (new Model\ShopQuery())
            ->setCode($shop->getCode())
            ->setName($shop->getName());

        $rows = [];

        foreach ($calc_query['rows'] as $item) {

            $product = new ProductDto(
                $item['product']['externalId'],
                $item['product']['sku'],
                $item['product']['title'],
                (float) $item['product']['blackPrice'],
                $item['product']['redPrice'] ?? 0
            );

            $productObj = (new Model\Product())
                ->setExternalId($product->getExternalId())
                ->setSku($product->getSku())
                ->setTitle($product->getTitle())
                ->setBlackPrice($product->getBlackPrice());

            if ($product->getRedPrice() != 0) {
                $productObj->setRedPrice($product->getRedPrice());
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