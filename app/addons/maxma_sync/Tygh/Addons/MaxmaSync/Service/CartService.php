<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Generated\Model\V2CalculatePurchaseResponse;
use Tygh\Addons\MaxmaSync\Dto\CalculationQueryDto;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
use Tygh\Addons\MaxmaSync\Dto\ProductDto;
use Tygh\Addons\MaxmaSync\Dto\RowDto;
use Tygh\Addons\MaxmaSync\Dto\ShopDto;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class CartService
{
    private string $default_phone = '+7 777 7777777';

    public function __construct(
        private readonly array $settings,
        private ?MaxmaClient   $maxmaClient = null,
        private readonly QueueRepository $queue_repository = new QueueRepository(),
        private readonly MaxmaLogger $logger = new MaxmaLogger()
    ) {
        $this->maxmaClient = $this->maxmaClient ?? new MaxmaClient($this->settings);
    }

    public function calculateCartContent($cart, $auth, $promotion_code = ''): V2CalculatePurchaseResponse|array
    {
        $calculationQuery = $this->generatecalculationQuery($cart, $auth, $promotion_code);
        $payload = [
            'calculationQuery' => $calculationQuery,
        ];

        try {
            return $this->maxmaClient->calculatePurchase($payload);
        } catch (ProcessingException $e) {
            if ($e->getCode() === ProcessingException::ERR_CLIENT_NOT_FOUND) {
                $client_update_dto = new ClientUpdateDto($auth['user_id']);
                $request = $client_update_dto::fromArray($auth['user_id'], $cart['user_data']);
                $this->queue_repository->add(RequestTypes::NEW_CLIENT, $auth['user_id'], $request->toArray());
            }
            $this->logger->error('Calculate Cart Content request returned error: ' . $e->getMessage(), [
                'request' => $payload,
            ]);
            return [];
        }
    }
    public function generateCalculationQuery($cart, $auth, $promotion_code = ''): array
    {
        $client = new ClientDto(
            $cart['user_data']['phone'] ?? $this->default_phone,
            (string) $auth['user_id']
        );
        $shop = new ShopDto(
            $this->settings['maxma_shop_code'],
            $this->settings['maxma_shop_name']
        );

       $rows = [];

        foreach ($cart['products'] as $key => $product) {
            $externalId = (string) ($product['product_id'] ?? $product['product_code'] ?? 'UNKNOWN');
            $sku        = (string) ($product['product_code'] ?? $product['product_id'] ?? 'SKU-UNKNOWN');
            $title      = (string) ($product['product'] ?? 'No title');
            $blackPrice = max(0.01, (float) ($product['base_price'] ?? 0));
            $qty        = max(1, (float) ($product['amount'] ?? 1));

            $rows[] = new RowDto(
                (string) $key,
                $qty,
                new ProductDto(
                    $externalId,
                    $sku,
                    $title,
                    (float) ($product['list_price'] ?? 0),
                    $blackPrice,
                    (float) ($product['display_price'] ?? $blackPrice)
                )
            );
        }

        $dto = new CalculationQueryDto(
            $client,
            $shop,
            $promotion_code,
            (int) ($cart['points_info']['in_use']['points'] ?? 0),
            (int) ($cart['points_info']['reward'] ?? 0),
            $rows
        );

        return $dto->toArray();
    }
    public function applyExternalBonuses(array $calculation, array &$cart)
    {
        $rows = $calculation['rows'];

        foreach ($cart['products'] as $key => $product) {
            foreach ($rows as $p_id => $row) {
                if ($p_id == $key) {
                    $discount = $row['total_discount'];

                    if (!empty($discount)) {
                        $cart['products'][$key]['discount'] = (int) $discount;
                    }
                }
            }
        }
        $cart['subtotal_discount'] = $calculation['total_discount'];

        return $rows;
    }

    public function calculationToArray(object $calculation): array
    {
        $result = $calculation->getCalculationResult();

        $rows = [];
        foreach ($result->getRows() as $row) {
            $rows[$row->getId()] = [
                'total_discount' => (int) $row->getTotalDiscount(),
                'discounts' => [
                    'auto' => $row->getDiscounts()?->getAuto() ?? 0,
                    'manual' => $row->getDiscounts()?->getManual() ?? 0,
                    'bonuses' => $row->getDiscounts()?->getBonuses() ?? 0,
                ],
            ];
        }

        return [
            'rows' => $rows,
            'total_discount' => (int) $result->getSummary()->getTotalDiscount(),
        ];
    }
}