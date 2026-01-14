<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Generated\Model\V2CalculatePurchaseResponse;
use Tygh\Addons\MaxmaSync\Dto\CalculationQueryDto;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
use Tygh\Addons\MaxmaSync\Dto\ProductDto;
use Tygh\Addons\MaxmaSync\Dto\RowDto;
use Tygh\Addons\MaxmaSync\Dto\ShopDto;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;

class CartService
{
    private string $default_phone = '+7 777 7777777';

    /**
     * @param array $settings Настройки модуля
     * @param UsersService|null $usersService Сервис работы с пользователями
     * @param MaxmaClient|null $maxmaClient Клиент для работы с Maxma API
     * @param MaxmaLogger $logger Логгер
     */
    private array $settings;
    private ?UsersService $usersService;
    private ?MaxmaClient $maxmaClient;
    private MaxmaLogger $logger;

    public function __construct(
        array $settings,
        ?UsersService $usersService = null,
        ?MaxmaClient $maxmaClient = null,
        ?MaxmaLogger $logger = null
    ) {
        $this->settings = $settings;
        $this->usersService = $usersService ?? new UsersService($this->settings);
        $this->maxmaClient = $maxmaClient ?? new MaxmaClient($this->settings);
        $this->logger = $logger ?? new MaxmaLogger();
    }

    /**
     * Рассчитывает содержимое корзины через Maxma API
     *
     * @param array $cart Данные корзины
     * @param array $auth Данные авторизации пользователя
     * @param string $promotion_code Промокод (опционально)
     * @return V2CalculatePurchaseResponse|array
     */
    public function calculateCartContent(array $cart, array $auth, string $promotion_code = '')
    {
        $calculationQuery = $this->generatecalculationQuery($cart, $promotion_code);
        $payload = [
            'calculationQuery' => $calculationQuery,
        ];

        try {
            return $this->maxmaClient->calculatePurchase($payload);
        } catch (ProcessingException $e) {
            if ($e->getCode() === ProcessingException::ERR_CLIENT_NOT_FOUND) {
                $this->usersService->queueNewClient($auth['user_id'], $cart['user_data']);
            }
            $this->logger->error('Calculate Cart Content request returned error: ' . $e->getMessage(), [
                'request' => $payload,
            ]);
            return [];
        }
    }

    /**
     * Генерирует массив запроса CalculationQueryDto для Maxma
     *
     * @param array $cart Данные корзины
     * @param string $promotion_code Промокод (опционально)
     * @return array
     */
    public function generateCalculationQuery(array $cart, string $promotion_code = ''): array
    {
        $client = new ClientDto(
            $cart['user_data']['phone'] ?? $this->default_phone
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
            $redPrice   = isset($product['display_price']) && $product['display_price'] != $blackPrice ? $product['display_price'] : 0;

            $rows[] = new RowDto(
                (string) $key,
                $qty,
                new ProductDto(
                    $externalId,
                    $sku,
                    $title,
                    $blackPrice,
                    $redPrice,
                    (float) ($product['list_price'] ?? 0),
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

    /**
     * Применяет внешние бонусы к корзине
     *
     * @param array $calculation Результат расчета корзины
     * @param array $cart Ссылка на корзину
     * @return array Обновленные строки корзины
     */
    public function applyExternalBonuses(array $calculation, array &$cart): array
    {
        $rows = $calculation['rows'];

        foreach ($cart['products'] as $key => $product) {
            foreach ($rows as $p_id => $row) {
                if ($p_id === (string)$key) {
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

    /**
     * Преобразует объект CalculationResult в массив
     *
     * @param object $calculation Результат расчета корзины
     * @return array
     */
    public function calculationToArray(object $calculation): array
    {
        $result = $calculation->getCalculationResult();

        $rows = [];
        foreach ($result->getRows() as $row) {
            $discounts = $row->getDiscounts();
            $rows[$row->getId()] = [
                'total_discount' => (int) $row->getTotalDiscount(),
                'discounts' => [
                    'auto'    => $discounts ? $discounts->getAuto() : 0,
                    'manual'  => $discounts ? $discounts->getManual() : 0,
                    'bonuses' => $discounts ? $discounts->getBonuses() : 0,
                ],
            ];
        }

        return [
            'rows' => $rows,
            'total_discount' => (int) $result->getSummary()->getTotalDiscount(),
        ];
    }

    /**
     * Обрабатывает результат расчета корзины и обновляет сессию/уведомления
     *
     * @param V2CalculatePurchaseResponse $result Результат расчета корзины
     * @param array $cart Ссылка на корзину
     * @param array $session Ссылка на сессию пользователя
     * @return void
     */
    public function handleCalculationResult(V2CalculatePurchaseResponse $result, array &$cart, &$session): void
    {
        $calc_result =  $result->getCalculationResult();
        $bonuses_error = $calc_result->getBonuses()->getError();

        if ($bonuses_error && $bonuses_error->getCode() === ProcessingException::ERR_INCORRECT_BONUS_AMOUNT) {
            unset($cart['points_info']['in_use']);
            fn_set_notification('W', __('warning'), $bonuses_error->getDescription());
        }

        $rows = $calc_result->getRows();

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $offers = $row->getOffers();
                if (!empty($offers)) {
                    $session['promotion_notices']['promotion']['applied'] = true;
                    foreach ($offers as $offer) {
                        $name = $offer->getName();
                        if (isset($session['shown_promo_notitifcation']) && in_array($name, $session['shown_promo_notitifcation'])) {
                            continue;
                        }
                        $session['promotion_notices']['promotion']['messages'][] = 'text_applied_promotions';
                        $session['promotion_notices']['promotion']['applied_promotions'][] = $offer->getName();
                        $session['shown_promo_notitifcation'][] = $offer->getName();
                    }
                }
            }
        }
        fn_check_promotion_notices();
    }
}
