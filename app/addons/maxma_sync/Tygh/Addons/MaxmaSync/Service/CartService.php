<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Exception\TransportException;
use CloudLoyalty\Api\Generated\Model\V2CalculatePurchaseResponse;
use Tygh\Addons\MaxmaSync\Dto\CalculationQueryDto;
use Tygh\Addons\MaxmaSync\Dto\ClientDto;
use Tygh\Addons\MaxmaSync\Dto\ProductDto;
use Tygh\Addons\MaxmaSync\Dto\RowDto;
use Tygh\Addons\MaxmaSync\Dto\ShopDto;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;

class CartService
{
    private readonly UsersService $usersService;
    private readonly MaxmaClient $maxmaClient;
    /**
     * @param array<array-key, mixed> $settings Настройки модуля
     * @param UsersService|null $usersService Сервис работы с пользователями
     * @param MaxmaClient|null $maxmaClient Клиент для работы с Maxma API
     * @param MaxmaLogger $logger Логгер
     */
    public function __construct(
        private readonly array $settings,
        ?UsersService $usersService = null,
        ?MaxmaClient $maxmaClient = null,
        private readonly MaxmaLogger $logger = new MaxmaLogger()
    ) {
        $this->maxmaClient = $maxmaClient ?? new MaxmaClient($this->settings);
        $this->usersService = $usersService ?? new UsersService($this->settings);
    }

    /**
     * Рассчитывает содержимое корзины через Maxma API
     *
     * @param array<array-key, mixed> $cart Данные корзины
     * @param array<array-key, mixed> $auth Данные авторизации пользователя
     * @param string $promotion_code Промокод (опционально)
     * @return ?V2CalculatePurchaseResponse
     */
    public function calculateCartContent(array $cart, array $auth, string $promotion_code = ''): ?V2CalculatePurchaseResponse
    {
        $calculationQuery = $this->generatecalculationQuery($cart, $promotion_code);
        if (!$calculationQuery['rows']) {
            return null;
        }
        $payload = [
            'calculationQuery' => $calculationQuery,
        ];

        try {
            return $this->maxmaClient->calculatePurchase($payload);
        } catch (ProcessingException | TransportException $e) {
            $user_data = $cart['user_data'] ?? null;
            if ($e instanceof ProcessingException && $e->getCode() === ProcessingException::ERR_CLIENT_NOT_FOUND
                && !empty($auth['user_id']) && is_array($user_data) && !empty($user_data['phone'])
            ) {
                $this->usersService->queueNewClient((int) $auth['user_id'], $user_data);
            }
            $this->logger->error('MAXMA cart calculation failed.', ['code' => $e->getCode()]);
            return null;
        }
    }

    /**
     * Генерирует массив запроса CalculationQueryDto для Maxma
     *
     * @param array<array-key, mixed> $cart Данные корзины
     * @param string $promotion_code Промокод (опционально)
     * @return array<array-key, mixed>
     */
    public function generateCalculationQuery(array $cart, string $promotion_code = ''): array
    {
        $phone = '';
        foreach (['phone', 'b_phone', 's_phone'] as $field) {
            $value = $cart['user_data'][$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $phone = trim($value);
                break;
            }
        }
        $client = $phone === '' ? null : new ClientDto($phone);
        $shop = new ShopDto(
            $this->settings['maxma_shop_code'],
            $this->settings['maxma_shop_name']
        );

        $rows = [];
        foreach ($cart['products'] ?? [] as $key => $product) {
            $externalId = (string) ($product['product_id'] ?? $product['product_code'] ?? 'UNKNOWN');
            $code       = (string) ($product['product_code'] ?? '');
            $sku        = $code !== '' ? $code : $externalId;
            $title      = (string) ($product['product'] ?? 'No title');
            $base_price = (float) ($product['base_price'] ?? $product['price'] ?? 0);
            $qty        = (float) ($product['amount'] ?? 1);
            $price      = (float) ($product['price'] ?? $base_price);
            $blackPrice = max($price, (float) ($product['original_price'] ?? $base_price));
            $redPrice   = $price < $blackPrice ? $price : null;

            $rows[] = new RowDto(
                (string) $key,
                $qty,
                new ProductDto(
                    $externalId,
                    $sku,
                    $title,
                    $blackPrice,
                    $redPrice,
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
     * @param array<array-key, mixed> $calculation Результат расчета корзины
     * @param array<array-key, mixed> $cart Ссылка на корзину
     * @return array<array-key, mixed> Обновленные строки корзины
     */
    public function applyExternalBonuses(array $calculation, array &$cart, float $native_points_cost = 0.0): array
    {
        self::clearExternalBonuses($cart);
        $rows = $calculation['rows'];
        $subtotal_discount = (float) ($cart['subtotal_discount'] ?? 0);
        $cart['maxma_applied_discount'] = [
            'subtotal' => $subtotal_discount,
            'applied_subtotal' => max(0.0, $subtotal_discount - $native_points_cost) + $calculation['total_discount'],
            'products' => [],
        ];

        foreach ($cart['products'] ?? [] as $key => $product) {
            $discount = $rows[$key]['total_discount'] ?? 0.0;
            $amount = (float) ($product['amount'] ?? 0);
            if ($discount <= 0 || $amount <= 0 || ($product['stored_price'] ?? 'N') === 'Y') {
                continue;
            }

            $previous = (float) ($product['discount'] ?? 0);
            $applied = $previous + $discount / $amount;
            $cart['maxma_applied_discount']['products'][$key] = [$previous, $applied];
            $cart['products'][$key]['discount'] = $applied;
        }

        // Replace the native points discount with the discount confirmed by MAXMA.
        $cart['subtotal_discount'] = $cart['maxma_applied_discount']['applied_subtotal'];
        return $rows;
    }

    /**
     * Discard this module's previous adjustment without replacing values already recalculated by core.
     * @param array<array-key, mixed> $cart
     */
    public static function clearExternalBonuses(array &$cart): void
    {
        $applied = $cart['maxma_applied_discount'] ?? null;
        if ($applied === null) {
            return;
        }

        if ((float) ($cart['subtotal_discount'] ?? 0) === $applied['applied_subtotal']) {
            $cart['subtotal_discount'] = $applied['subtotal'];
        }
        foreach ($applied['products'] as $key => [$previous, $updated]) {
            if (isset($cart['products'][$key]['discount']) && (float) $cart['products'][$key]['discount'] === $updated) {
                $cart['products'][$key]['discount'] = $previous;
            }
        }
        unset($cart['maxma_applied_discount']);
    }

    /**
     * Remove the module's promo code while preserving native coupon entries.
     * @param array<array-key, mixed> $cart
     */
    public static function clearCoupon(array &$cart): void
    {
        $coupon = $cart['maxma_promotion_data']['coupon'] ?? '';
        if ($coupon !== '' && ($cart['coupons'][$coupon] ?? null) === 1) {
            unset($cart['coupons'][$coupon]);
        }
        unset($cart['maxma_promotion_data']);
    }

    /**
     * Преобразует объект CalculationResult в массив
     *
     * @param V2CalculatePurchaseResponse $calculation Результат расчета корзины
     * @return array<array-key, mixed>
     */
    public function calculationToArray(V2CalculatePurchaseResponse $calculation): array
    {
        $result = $calculation->getCalculationResult();

        $rows = [];
        foreach (($result?->getRows() ?? []) as $row) {
            $rows[$row->getId()] = [
                'total_discount' => $row->getTotalDiscount(),
                'discounts' => [
                    'auto' => $row->getDiscounts()?->getAuto() ?? 0,
                    'manual' => $row->getDiscounts()?->getManual() ?? 0,
                    'bonuses' => $row->getDiscounts()?->getBonuses() ?? 0,
                ],
            ];
        }

        return [
            'rows' => $rows,
            'total_discount' => (float) ($result?->getSummary()?->getTotalDiscount() ?? 0),
        ];
    }

    /**
     * Обрабатывает результат расчета корзины и обновляет сессию/уведомления
     *
     * @param V2CalculatePurchaseResponse $result Результат расчета корзины
     * @param array<array-key, mixed> $cart Ссылка на корзину
     * @param array<array-key, mixed> $session Ссылка на сессию пользователя
     * @return void
     */
    public function handleCalculationResult(V2CalculatePurchaseResponse $result, array &$cart, &$session): void
    {
        $calc_result =  $result->getCalculationResult();
        $bonuses_error = $calc_result?->getBonuses()?->getError();

        if ($bonuses_error && $bonuses_error->getCode() === ProcessingException::ERR_INCORRECT_BONUS_AMOUNT) {
            unset($cart['points_info']['in_use']);
            fn_set_notification('W', __('warning'), $bonuses_error->getDescription());
        }

        $rows = $calc_result?->getRows();

        if ($rows !== null && $rows !== []) {
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
