<?php

use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Registry;
use Tygh\Enum\OrderStatuses;
use Tygh\Enum\SiteArea;
use Tygh\Enum\Addons\Rma\ReturnOperationStatuses;

/**
 * Добавление или обновление данных пользователя в очередь Maxma Sync
 *
 * @param string $action 'add' для нового пользователя, иначе обновление
 * @param array $user_data Данные пользователя
 * @return void
 */
function fn_maxma_sync_update_profile($action, $user_data)
{
    $user_id = $user_data['user_id'];
    $request = ClientUpdateDto::fromArray($user_id, $user_data);

    $request_type = $action === 'add'
        ? RequestTypes::NEW_CLIENT
        : RequestTypes::UPDATE_CLIENT;

    (new QueueRepository())->add($request_type, $user_id, $request->toArray());
}

/**
 * Добавление заказа в очередь после оформления
 *
 * @param array $cart Корзина пользователя
 * @param array $auth Данные авторизации пользователя
 * @param string $action Действие ('save' или 'place')
 * @param int $issuer_id ID инициатора заказа
 * @param int $parent_order_id ID родительского заказа
 * @param int $order_id ID текущего заказа
 * @return void
 */
function fn_maxma_sync_place_order_post(array $cart, array $auth, string $action, int $issuer_id, int $parent_order_id, int $order_id): void
{
    $settings = Registry::get('addons.maxma_sync');
    $cart_service = new CartService($settings);
    $maxma_promotion_data = !empty($cart['maxma_promotion_data']) ? $cart['maxma_promotion_data'] : '';

    if ($action === 'save') {
        $maxma_promotion_data = db_get_field("SELECT maxma_promotion_data FROM ?:orders WHERE order_id = ?i", $order_id);

        if ($maxma_promotion_data) {
            $maxma_promotion_data = unserialize($maxma_promotion_data);
        }
    }

    $promo_code = !empty($maxma_promotion_data['coupon']) ? $maxma_promotion_data['coupon'] : '';

    $payload = [
        'orderId' => (string)$order_id,
        'calculationQuery' => $cart_service->generatecalculationQuery($cart, $auth, $promo_code),
    ];
    (new QueueRepository())->add(RequestTypes::SET_ORDER, $order_id, $payload);

    if (isset(Tygh::$app['session']['shown_promo_notitifcation'])) {
        unset(Tygh::$app['session']['shown_promo_notitifcation']);
    }
}

/**
 * Обновление корзины перед обновлением заказа
 *
 * @param array $cart Корзина пользователя (по ссылке)
 * @return void
 */
function fn_maxma_sync_pre_update_order(array &$cart): void
{
    $settings = Registry::get('addons.maxma_sync');
    $cart_service = new CartService($settings);
    $new_calculation = !empty($cart['maxma_promotion_data']['total_discount']) ? $cart['maxma_promotion_data'] : false;

    if (empty($new_calculation)) {
        $new_calculation = $cart_service->calculateCartContent($cart, $cart['user_data']);
    }

    if ($new_calculation) {
        $new_calculation = is_array($new_calculation) ? $new_calculation : $cart_service->calculationToArray($new_calculation);

        if (!empty($new_calculation['total_discount'])) {
            $cart_service->applyExternalBonuses($new_calculation, $cart);

            if (!empty($cart['subtotal_discount'])) {
                $cart['discounted_subtotal'] = $cart['subtotal'] - (min($cart['subtotal_discount'], $cart['subtotal']));
                $cart['total'] -= ($cart['subtotal_discount'] < $cart['total']) ? $cart['subtotal_discount'] : $cart['total'];
            }
        }
    }
}

/**
 * Сериализация данных Maxma при создании заказа
 *
 * @param array $order Данные заказа (по ссылке)
 * @return void
 */
function fn_maxma_sync_create_order(array &$order): void
{
    if (!empty($order['maxma_promotion_data'])) {
        $order['maxma_promotion_data'] = serialize($order['maxma_promotion_data']);
    }
}

/**
 * Обработка изменения статуса заказа
 *
 * @param int $order_id ID заказа
 * @param string $status_to Новый статус заказа
 * @return void
 */
function fn_maxma_sync_change_order_status_post(int $order_id, string $status_to): void
{
    $status_map = [
        RequestTypes::CONFIRM_ORDER => Registry::get('addons.maxma_sync.maxma_confirm_statuses'),
        RequestTypes::CANCEL_ORDER  => Registry::get('addons.maxma_sync.maxma_confirm_cancel'),
        RequestTypes::APPLY_RETURN  => Registry::get('addons.maxma_sync.maxma_return_statuses'),
    ];

    $queue_type = null;

    foreach ($status_map as $request_type => $statuses) {
        $status_list = array_map('trim', explode(',', (string) $statuses));

        if (in_array($status_to, $status_list, true)) {
            $queue_type = $request_type;
            break;
        }
    }

    if (!$queue_type) {
        return;
    }

    $queue = new QueueRepository();

    if ($queue_type !== RequestTypes::APPLY_RETURN) {
        $queue->add($queue_type, $order_id, ['orderId' => $order_id]);
        return;
    }

    $returns = db_get_array(
        'SELECT * FROM ?:rma_returns WHERE order_id = ?i AND status = ?s',
        $order_id,
        ReturnOperationStatuses::APPROVED
    );

    $data = [];

    foreach ($returns as $return) {
        $items = db_get_array(
            'SELECT rrp.price,
                    rrp.amount AS itemCount,
                    p.product_code AS sku
             FROM ?:rma_return_products rrp
             LEFT JOIN ?:products p ON p.product_id = rrp.product_id
             WHERE return_id = ?i',
            $return['return_id']
        );

        $refund = 0;
        foreach ($items as $item) {
            $refund += $item['price'];
        }
        $settings = Registry::get('addons.maxma_sync');
        $data = [
            'id'           => $return['return_id'],
            'executedAt'   => $return['timestamp'],
            'shopCode'     => $settings['shop_code'],
            'shopName'     => $settings['shop_name'],
            'purchaseId'   => $order_id,
            'items'        => $items,
            'refundAmount' => $refund,
        ];
    }

    $queue->add($queue_type, $order_id, $data);
}

/**
 * Получение информации о пользователе для Maxma
 *
 * @param int $user_id ID пользователя
 * @param bool $get_profile Флаг получения профиля
 * @param int $profile_id ID профиля
 * @param array $user_data Данные пользователя (по ссылке)
 * @return void
 */
function fn_maxma_sync_get_user_info(int $user_id, bool $get_profile, int $profile_id, array &$user_data): void
{
    if (!$user_id || !$user_data['phone']) {
        return;
    }
    $user_service = new UsersService(Registry::get('addons.maxma_sync'), Tygh::$app['session']['auth']);

    $user_data['maxma_data'] = [
        'balance' => $user_service->getUserBonusesData($user_id, $user_data, $user_service::BALANCE_CACHE_KEY),
        'history' => $user_service->getUserBonusesData($user_id, $user_data, $user_service::HISTORY_CACHE_KEY),
    ];
}

/**
 * Получение ссылки для обработки очереди Maxma
 *
 * @return string
 */
function fn_maxma_sync_queue(): string
{
    return __(
        'maxma_sync.process_queue',
        [
            '[process_queue_url]' => fn_url('maxma.process_queue', SiteArea::ADMIN_PANEL),
        ]
    );
}

/**
 * Получение ссылки для обновления балансов
 *
 * @return string
 */
function fn_maxma_sync_update_balances(): string
{
    return __(
        'maxma_sync.update_balances',
        [
            '[update_balances_url]' => fn_url('maxma.update_balances', SiteArea::ADMIN_PANEL),
        ]
    );
}

/**
 * Расчет содержимого корзины
 *
 * @param array $cart Корзина пользователя (по ссылке)
 * @param array $auth Данные авторизации пользователя
 * @return void
 */
function fn_maxma_sync_calculate_cart_content_before_shipping_calculation(array &$cart, array $auth): void
{
    if (in_array(Registry::get('runtime.mode'), ['cart', 'checkout'])) {
        $settings = Registry::get('addons.maxma_sync');
        $cart_service = new CartService($settings);

        $new_calculation = !empty($cart['maxma_promotion_data']['total_discount']) ? $cart['maxma_promotion_data'] : false;

        if (empty($new_calculation)) {
            $new_calculation = $cart_service->calculateCartContent($cart, $auth);
        }

        if ($new_calculation) {
            $session = &Tygh::$app['session'];
            $cart_service->handleCalculationResult($new_calculation, $cart, $session);
            $new_calculation = is_array($new_calculation) ? $new_calculation : $cart_service->calculationToArray($new_calculation);

            if (!empty($new_calculation['total_discount']) && !empty($cart['maxma_promotion_data']['total_discount'])) {
                $cart['has_coupons'] = true;
                $cart['coupons'][$cart['maxma_promotion_data']['coupon']] = 1;
            }
            $cart_service->applyExternalBonuses($new_calculation, $cart);
        }
    }
}

/**
 * Проверка и применение купона перед акцией
 *
 * @param string $pending_coupon Купон
 * @param array $cart Корзина пользователя (по ссылке)
 * @return void
 */
function fn_maxma_sync_pre_promotion_check_coupon(string $pending_coupon, array &$cart)
{
    $settings = Registry::get('addons.maxma_sync');
    $cart_service = new CartService($settings);

    $new_calculation = $cart_service->calculateCartContent($cart, $cart['user_data'], $pending_coupon);
    $cart['maxma_promotion_data'] = $cart_service->calculationToArray($new_calculation);

    if (!empty($cart['maxma_promotion_data']['total_discount'])) {
        $cart['pending_coupon'] = false;
        $cart['maxma_promotion_data']['coupon'] = $pending_coupon;
    }
}
