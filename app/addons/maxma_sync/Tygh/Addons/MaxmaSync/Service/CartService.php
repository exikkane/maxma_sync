<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;

class CartService
{
    private array $settings;
    private string $default_phone = '+7 777 7777777';
    private MaxmaClient $maxmaClient;

    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->maxmaClient = new MaxmaClient($settings);
    }

    public function calculateCartContent($cart, $order_id = 0, $promotion_code = '')
    {
        $calculationQuery = $this->generatecalculationQuery($cart, $promotion_code);
        try {
            $payload = [
                'calculationQuery' => $calculationQuery,
            ];

            if (!empty($order_id)) {
                $payload['order_id'] = $order_id;
            }

            return $this->maxmaClient->calculatePurchase($payload);
        } catch (ProcessingException $e) {

            return [$e->getMessage(),$e->getHint()];
        }
    }
    public function generateCalculationQuery($cart, $promotion_code = '')
    {
        $data = [
            'client' => [
                'phoneNumber' => $cart['user_data']['phone'] ?? $this->default_phone,
                'externalId'  => (string) ($cart['user_data']['user_id'] ?? 'UNKNOWN'),
            ],
            'shop' => [
                'code' => 'CS-Cart',
                'name' => 'CS-Cart'
            ],
            'promocode' => $promotion_code,
            'applyBonuses' => $cart['points_info']['in_use']['points'] ?? 0,
            'collectBonuses' => $cart['points_info']['reward'] ?? 0
        ];

        $data['rows'] = [];

        foreach ($cart['products'] as $key => $product) {
            $externalId = (string) ($product['product_id'] ?? $product['product_code'] ?? 'UNKNOWN');
            $sku        = (string) ($product['product_code'] ?? $product['product_id'] ?? 'SKU-UNKNOWN');
            $title      = (string) ($product['product'] ?? 'No title');
            $blackPrice = max(0.01, (float) ($product['base_price'] ?? 0));
            $qty        = max(1, (float) ($product['amount'] ?? 1));

            $data['rows'][] = [
                'id' => (string)$key,
                'qty' => $qty,
                'product' => [
                    'externalId' => $externalId,
                    'sku'        => $sku,
                    'title'      => $title,
                    'buyingPrice'=> (float) ($product['list_price'] ?? 0),
                    'blackPrice' => $blackPrice,
                    'redPrice'   => (float) ($product['display_price'] ?? $blackPrice),
                ]
            ];
        }

        return $data;
    }
}