<?php

use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Registry;
use Tygh\Tygh;
use Tygh\Enum\OrderStatuses;
use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;
use Tygh\Enum\SiteArea;
use Tygh\Addons\MaxmaSync\Service\ReturnService;

/**
 * Добавление или обновление данных пользователя в очередь Maxma Sync
 *
 * @param string $action 'add' для нового пользователя, иначе обновление
 * @param array<array-key, mixed> $user_data Данные пользователя
 * @return void
 */
function fn_maxma_sync_update_profile($action, $user_data)
{
    $user_id = (int) ($user_data['user_id'] ?? 0);
    if ($user_id <= 0 || trim($user_data['phone'] ?? '') === '') {
        return;
    }
    $settings = Registry::get('addons.maxma_sync');

    $shop = [
        'shopCode' => $settings['maxma_shop_code'] ?? '',
        'shopName' => $settings['maxma_shop_name'] ?? '',
    ];

    $request = ClientUpdateDto::fromArray($user_id, $user_data, $shop);

    $request_type = $action === 'add'
        ? RequestTypes::NEW_CLIENT
        : RequestTypes::UPDATE_CLIENT;

    (new QueueRepository())->add($request_type, $user_id, $request->toArray());
}

/**
 * Добавление заказа в очередь после оформления
 *
 * @param array<array-key, mixed> $cart Корзина пользователя
 * @param array<array-key, mixed> $auth Данные авторизации пользователя
 * @param string $action Действие ('save' или 'place')
 * @param int $issuer_id ID инициатора заказа
 * @param int $parent_order_id ID родительского заказа
 * @param int $order_id ID текущего заказа
 * @return void
 */
function fn_maxma_sync_place_order_post(array $cart, array $auth, string $action, $issuer_id, int $parent_order_id, int $order_id, string $order_status = ''): void
{
    $placement_key = 'runtime.maxma_sync.placement_' . $order_id;
    $placement_status = Registry::get($placement_key);
    Registry::del($placement_key);
    Registry::del('runtime.maxma_sync.edit_' . $order_id);
    if ($order_status === OrderStatuses::PARENT) {
        return;
    }
    $settings = (array) Registry::get('addons.maxma_sync');
    $cart_service = new CartService($settings);
    $promo_code = $cart['maxma_promotion_data']['coupon'] ?? '';
    $query = $cart_service->generateCalculationQuery($cart, $promo_code);
    if (empty($query['client']) || !$query['rows']) {
        return;
    }

    $payload = [
        'orderId' => (string) $order_id,
        'calculationQuery' => $query,
    ];
    $queue = new QueueRepository();
    if ($queue->latestStatus(RequestTypes::CONFIRM_ORDER, $order_id) === QueueStatuses::DONE
        || $queue->latestStatus(RequestTypes::CANCEL_ORDER, $order_id) === QueueStatuses::DONE
    ) {
        return;
    }
    $queue->add(RequestTypes::SET_ORDER, $order_id, $payload);
    if (is_string($placement_status) && $placement_status === $order_status) {
        fn_maxma_sync_change_order_status_post($order_id, $placement_status);
    }

    if (isset(Tygh::$app['session']['shown_promo_notitifcation'])) {
        unset(Tygh::$app['session']['shown_promo_notitifcation']);
    }
}

/**
 * Обновление корзины перед обновлением заказа
 *
 * @param array<array-key, mixed> $cart Корзина пользователя (по ссылке)
 * @return void
 */
function fn_maxma_sync_pre_update_order(array &$cart, int $order_id = 0): void
{
    if ($order_id > 0) {
        Registry::set('runtime.maxma_sync.edit_' . $order_id, true);
    }
    if (Registry::get('runtime.controller') === 'checkout') {
        return;
    }
    if (!isset($cart['maxma_applied_discount']) && empty($cart['maxma_promotion_data']['coupon'])) {
        return;
    }
    $service = new CartService((array) Registry::get('addons.maxma_sync'));
    $coupon = $cart['maxma_promotion_data']['coupon'] ?? '';
    $fingerprint = hash('sha256', serialize($service->generateCalculationQuery($cart, $coupon)));
    if (($cart['maxma_applied_discount']['fingerprint'] ?? null) !== $fingerprint) {
        fn_maxma_sync_recalculate_cart($service, $cart, $cart['user_data'] ?? []);
    }
}

/**
 * Сериализация данных Maxma при создании заказа
 *
 * @param array<array-key, mixed> $order Данные заказа (по ссылке)
 * @return void
 */
function fn_maxma_sync_create_order(array &$order): void
{
    $order['maxma_promotion_data'] = !empty($order['maxma_promotion_data'])
        ? serialize($order['maxma_promotion_data'])
        : '';
}

/**
 * Restore the coupon and remove the previously saved MAXMA part before recalculating an order.
 *
 * @param array<array-key, mixed> $order_info
 * @param array<array-key, mixed> $cart
 * @param array<array-key, mixed> $auth
 */
function fn_maxma_sync_form_cart(array $order_info, array &$cart, array $auth): void
{
    $serialized = $order_info['maxma_promotion_data'] ?? null;
    if (!is_string($serialized) || $serialized === '') {
        return;
    }
    $data = @unserialize($serialized, ['allowed_classes' => false]);
    if (!is_array($data)) {
        return;
    }

    $discount = $data['total_discount'] ?? null;
    if (is_numeric($discount) && (float) $discount > 0 && isset($cart['original_subtotal_discount'])) {
        $native = fn_format_price(max(0.0, (float) $cart['original_subtotal_discount'] - (float) $discount));
        $cart['subtotal_discount'] = $cart['original_subtotal_discount'] = $native;
    }

    $coupon = $data['coupon'] ?? null;
    if (is_string($coupon) && $coupon !== '') {
        $cart['maxma_promotion_data'] = ['coupon' => $coupon];
    }
}

/**
 * Persist MAXMA promotion metadata when editing an existing order.
 * @param array<array-key, mixed> $order
 */
function fn_maxma_sync_update_order(array &$order, int $order_id): void
{
    fn_maxma_sync_create_order($order);
}

/**
 * Обработка изменения статуса заказа
 *
 * @param int $order_id ID заказа
 * @param string $status_to Новый статус заказа
 * @return void
 * @param array<array-key, mixed> $force_notification
 * @param array<array-key, mixed> $order_info
 */
function fn_maxma_sync_change_order_status_post(
    int $order_id,
    string $status_to,
    string $status_from = '',
    array|bool $force_notification = [],
    bool $place_order = false,
    array $order_info = []
): void
{
    if (($order_info['is_parent_order'] ?? 'N') === 'Y') {
        return;
    }
    if ($place_order) {
        Registry::set('runtime.maxma_sync.placement_' . $order_id, $status_to);
        return;
    }
    if ($status_to === OrderStatuses::INCOMPLETED && Registry::get('runtime.maxma_sync.edit_' . $order_id) === true) {
        return;
    }
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

    if ($order_info && empty($order_info['phone']) && empty($order_info['b_phone']) && empty($order_info['s_phone'])) {
        return;
    }
    $queue = new QueueRepository();

    if ($queue_type !== RequestTypes::APPLY_RETURN) {
        $lock = 'order-status:' . $order_id;
        if (!$queue->acquireLock($lock)) {
            throw new RuntimeException('Could not obtain the MAXMA order status queue lock.');
        }
        try {
            $intent = $queue->latestTerminalIntent($order_id);
            if ($intent === null || $intent['type'] !== $queue_type) {
                $queue->add($queue_type, $order_id, ['orderId' => (string) $order_id]);
            }
        } finally {
            $queue->releaseLock($lock);
        }
        return;
    }

    if (Registry::get('addons.rma.status') === 'A') {
        /** @var ReturnService $service */
        $service = Tygh::$app['addons.maxma_sync.returns'];
        $service->enqueueApprovedReturns($order_id, (array) Registry::get('addons.maxma_sync'));
    }
}

/**
 * Получение информации о пользователе для Maxma
 *
 * @param int $user_id ID пользователя
 * @param bool $get_profile Флаг получения профиля
 * @param int $profile_id ID профиля
 * @param array<array-key, mixed> $user_data Данные пользователя (по ссылке)
 * @return void
 */
function fn_maxma_sync_get_user_info(int $user_id, bool $get_profile, ?int $profile_id, array &$user_data): void
{
    if (!$user_id || empty($user_data['phone'])) {
        return;
    }
    $session = Tygh::$app['session'];
    $cache = (array) ($session['maxma_sync_cache'] ?? []);
    $user_service = new UsersService((array) Registry::get('addons.maxma_sync'), $cache);

    $user_data['maxma_data'] = [
        'balance' => $user_service->getUserBonusesData($user_id, $user_data, $user_service::BALANCE_CACHE_KEY),
        'history' => $user_service->getUserBonusesData($user_id, $user_data, $user_service::HISTORY_CACHE_KEY),
    ];
    $session['maxma_sync_cache'] = $cache;
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
 * Restore the native discount before CS-Cart applies its own promotions.
 * @param array<array-key, mixed> $auth
 * @param array<array-key, mixed> $cart
 * @param array<array-key, mixed> $cart_products
 */
function fn_maxma_sync_calculate_cart_items(array &$cart, array $cart_products, array $auth, bool $apply_cart_promotions): void
{
    if (!fn_maxma_sync_is_cart_calculation()) {
        return;
    }
    if (Registry::get('runtime.controller') === 'checkout'
        && Registry::get('runtime.mode') === 'place_order'
        && Registry::get('runtime.maxma_sync.previous_discount') === null
    ) {
        Registry::set('runtime.maxma_sync.previous_discount', (float) ($cart['maxma_promotion_data']['total_discount'] ?? 0));
    }

    CartService::clearExternalBonuses($cart);
    $pending = $cart['pending_coupon'] ?? null;
    if (!$apply_cart_promotions || !is_string($pending) || $pending === '' || isset($cart['coupons'][$pending])) {
        return;
    }
    if (Registry::get('settings.General.use_single_coupon') === 'Y' && !empty($cart['coupons'])) {
        return;
    }

    $service = new CartService((array) Registry::get('addons.maxma_sync'));
    $candidate_cart = $cart;
    foreach ($cart_products as $key => $product) {
        if (isset($product['price'], $product['original_price'], $candidate_cart['products'][$key])) {
            $candidate_cart['products'][$key]['price'] = $product['price'];
            $candidate_cart['products'][$key]['original_price'] = $product['original_price'];
            $candidate_cart['products'][$key]['product_code'] ??= $product['product_code'] ?? '';
        }
    }
    $response = $service->calculateCartContent($candidate_cart, $auth, $pending);
    if ($response?->getCalculationResult()?->getPromocode()?->getApplied() === true) {
        CartService::clearCoupon($cart);
        $cart['maxma_promotion_data'] = ['coupon' => $pending];
        unset($cart['pending_coupon'], $cart['pending_original_coupon']);
    }
}

/**
 * Restrict the integration to checkout and the admin order editor.
 */
function fn_maxma_sync_is_cart_calculation(): bool
{
    $controller = Registry::get('runtime.controller');
    $mode = Registry::get('runtime.mode');
    return ($controller === 'checkout' && in_array($mode, ['cart', 'checkout', 'point_payment', 'place_order', 'complete'], true))
        || ($controller === 'order_management' && in_array($mode, ['add', 'update', 'place_order'], true));
}

/**
 * Apply MAXMA after core has finished calculating native promotions and points.
 *
 * @param array<array-key, mixed> $cart
 * @param array<array-key, mixed> $cart_products
 * @param array<array-key, mixed> $auth
 */
function fn_maxma_sync_calculate_cart(array &$cart, array $cart_products, array $auth): void
{
    if (!fn_maxma_sync_is_cart_calculation() || empty($cart['products'])) {
        return;
    }
    foreach ($cart_products as $key => $product) {
        if (isset($product['original_price'], $cart['products'][$key])) {
            $cart['products'][$key]['original_price'] = $product['original_price'];
            $cart['products'][$key]['product_code'] ??= $product['product_code'] ?? '';
        }
    }
    fn_maxma_sync_recalculate_cart(new CartService((array) Registry::get('addons.maxma_sync')), $cart, $auth);
}

/**
 * Recalculate against the current cart and drop stale MAXMA adjustments on a rejected calculation.
 * @param array<array-key, mixed> $auth
 * @param array<array-key, mixed> $cart
 */
function fn_maxma_sync_recalculate_cart(CartService $service, array &$cart, array $auth): void
{
    $before = (float) ($cart['subtotal_discount'] ?? 0);
    CartService::clearExternalBonuses($cart);
    $native_points_cost = (float) ($cart['points_info']['in_use']['cost'] ?? 0);
    $coupon = $cart['maxma_promotion_data']['coupon'] ?? '';
    $response = $service->calculateCartContent($cart, $auth, $coupon);
    if ($response === null) {
        CartService::clearCoupon($cart);
        if (isset($cart['points_info']['in_use'])) {
            $cart['subtotal_discount'] = max(0.0, (float) ($cart['subtotal_discount'] ?? 0) - $native_points_cost);
            unset($cart['points_info']['in_use']);
        }
        fn_maxma_sync_update_cart_total($cart, $before);
        return;
    }
    if ($coupon !== '' && $response->getCalculationResult()?->getPromocode()?->getApplied() !== true) {
        CartService::clearCoupon($cart);
        $coupon = '';
    }

    $session = Tygh::$app['session'];
    $service->handleCalculationResult($response, $cart, $session);
    if (!isset($cart['points_info']['in_use']) && $native_points_cost > 0) {
        $cart['subtotal_discount'] = max(0.0, (float) ($cart['subtotal_discount'] ?? 0) - $native_points_cost);
        $native_points_cost = 0.0;
    }
    $calculation = $service->calculationToArray($response);
    if ($coupon !== '') {
        $calculation['coupon'] = $coupon;
        $cart['has_coupons'] = true;
        $cart['coupons'][$coupon] = 1;
    }
    $cart['maxma_promotion_data'] = $calculation;
    $service->applyExternalBonuses($calculation, $cart, $native_points_cost);
    fn_maxma_sync_update_cart_total($cart, $before);
    $cart['maxma_applied_discount']['fingerprint'] = hash('sha256', serialize($service->generateCalculationQuery($cart, $coupon)));
}

/**
 * Require customer confirmation if the MAXMA discount fell during checkout submission.
 *
 * @param array<array-key, mixed> $cart
 */
function fn_maxma_sync_pre_place_order(array $cart, bool &$allow): void
{
    if (!empty($cart['parent_order_id'])) {
        return;
    }
    $previous_discount = Registry::get('runtime.maxma_sync.previous_discount');
    $current_discount = $cart['maxma_promotion_data']['total_discount'] ?? 0;
    if ($allow && is_float($previous_discount) && is_numeric($current_discount)
        && fn_format_price($previous_discount) > fn_format_price((float) $current_discount)
    ) {
        $allow = false;
        fn_set_notification('W', __('warning'), __('maxma_sync.checkout_discount_changed'));
    }
}

/**
 * Update the totals calculated by core, applying only the difference in discounts.
 *
 * @param array<array-key, mixed> $cart
 */
function fn_maxma_sync_update_cart_total(array &$cart, float $before): void
{
    if (!isset($cart['subtotal'], $cart['total'])) {
        return;
    }
    $subtotal = (float) $cart['subtotal'];
    $after = min((float) ($cart['subtotal_discount'] ?? 0), $subtotal);
    $cart['discounted_subtotal'] = fn_format_price($subtotal - $after);
    $cart['total'] = fn_format_price(max(0.0, (float) $cart['total'] + min($before, $subtotal) - $after));
}
