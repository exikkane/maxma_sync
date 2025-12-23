<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

use CloudLoyalty\Api\Generated\Model;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class RequestFactory
{
    /**
     * Создать объект запроса SDK по названию метода и массиву данных
     */
    public static function make(string $method, array $payload)
    {
        switch ($method) {
            case RequestTypes::NEW_CLIENT:
                if (!isset($payload['client'])) {
                    throw new \InvalidArgumentException('Payload for NEW_CLIENT must contain "client" key.');
                }

                $c = $payload['client'];
                $clientObj = (new Model\ClientInfoQuery())
                    ->setEmail($c['email'] ?? '')
                    ->setPhoneNumber($c['phoneNumber'] ?? '')
                    ->setName($c['name'] ?? '')
                    ->setSurname($c['surname'] ?? '')
                    ->setFullName($c['name'] . ' ' . $c['surname'] ?? '')
                    ->setExternalId((string) $c['user_id'] ?? '');

                $shopObj = (new Model\ShopQuery())
                    ->setCode($payload['calculationQuery']['shop']['code'] ?? 'CS-Cart')
                    ->setName($payload['calculationQuery']['shop']['name'] ?? 'CS-Cart');

                return (new Model\NewClientRequest())
                    ->setClient($clientObj)
                    ->setShop($shopObj);
            case RequestTypes::UPDATE_CLIENT:
                if (!isset($payload['client'])) {
                    throw new \InvalidArgumentException('Payload for UPDATE_CLIENT must contain "client" key.');
                }

                $c = $payload['client'];
                $clientObj = (new Model\ClientInfoQuery())
                    ->setEmail($c['email'] ?? '')
                    ->setPhoneNumber($c['phoneNumber'] ?? '')
                    ->setName($c['name'] ?? '')
                    ->setSurname($c['surname'] ?? '')
                    ->setFullName($c['name'] . ' ' . $c['surname'] ?? '')
                    ->setExternalId((string) $c['user_id'] ?? '');

                return (new Model\UpdateClientRequest())
                    ->setPhoneNumber($c['phoneNumber'] ?? '')
                    ->setExternalId($c['user_id'] ?? '')
                    ->setClient($clientObj);

            case RequestTypes::GET_BALANCE:
                if (!isset($payload['phoneNumber'])) {
                    throw new \InvalidArgumentException('Payload for GET_BALANCE must contain "phoneNumber" key.');
                }
                return (new Model\ClientQuery())
                    ->setPhoneNumber($payload['phoneNumber'])
                    ->setExternalId((string) $payload['externalId'] ?? '');

            case RequestTypes::GET_BONUS_HISTORY:
                if (!isset($payload['phoneNumber'])) {
                    throw new \InvalidArgumentException('Payload for GET_BONUS_HISTORY must contain "phoneNumber" key.');
                }
                $clientObj = (new Model\ClientQuery())
                    ->setPhoneNumber($payload['phoneNumber'])
                    ->setExternalId((string) $payload['externalId'] ?? '');

                return (new Model\GetBonusHistoryRequest())
                    ->setClient($clientObj);

            case RequestTypes::CALCULATE_PURCHASE:
            case RequestTypes::SET_ORDER:
                if (!isset($payload['calculationQuery'])) {
                    throw new \InvalidArgumentException('Payload for CALCULATE_PURCHASE and SET_ORDER must contain "calculationQuery" key.');
                }
                if ($method === RequestTypes::SET_ORDER) {
                    $request = new Model\V2SetOrderRequest();
                } else {
                    $request = new Model\V2CalculatePurchaseRequest();
                }

                $clientObj = (new Model\ClientQuery())
                    ->setPhoneNumber($payload['calculationQuery']['client']['phoneNumber'] ?? '')
                    ->setExternalId((string) $payload['calculationQuery']['client']['externalId'] ?? '');

                $shopObj = (new Model\ShopQuery())
                    ->setCode($payload['calculationQuery']['shop']['code'] ?? 'CS-Cart')
                    ->setName($payload['calculationQuery']['shop']['name'] ?? 'CS-Cart');
                $rows = [];


                foreach ($payload['calculationQuery']['rows'] as $item) {
                    $product = $item['product'];
                    $productObj = (new Model\Product())
                        ->setExternalId((string) $product['externalId'])
                        ->setSku((string) $product['sku'])
                        ->setTitle((string) $product['title'])
                        ->setBuyingPrice((float) ($product['buyingPrice'] ?? 0))
                        ->setBlackPrice((float) ($product['blackPrice'] ?? 0))
                        ->setRedPrice((float) ($product['redPrice'] ?? 0));

                    $row = (new Model\CalculationQueryRow())
                        ->setId((string) $item['id'])
                        ->setQty((float) $item['qty'])
                        ->setProduct($productObj);

                    $rows[] = $row;
                }

                $calculationQueryObj = (new Model\CalculationQuery())
                    ->setClient($clientObj)
                    ->setShop($shopObj)
                    ->setRows($rows)
                    ->setApplyBonuses(123)// TODO
                    ->setCollectBonuses(123);// TODO

                if (isset($payload['promocode'])) {
                    $calculationQueryObj->setPromocode($payload['promocode']);
                }

                if (isset($payload['orderId'])) {
                    $request->setOrderId($payload['orderId']);
                }

                return $request->setCalculationQuery($calculationQueryObj);

            default:
                throw new \InvalidArgumentException("Unknown MAXMA method: {$method}");
        }
    }
}
