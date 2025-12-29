<?php

use CloudLoyalty\Api\Generated\Model\V2CalculatePurchaseResponse;
use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Registry;
use Tygh\Enum\OrderStatuses;
use Tygh\Enum\SiteArea;

function fn_maxma_sync_update_profile($action, $user_data)
{
    $user_id = $user_data['user_id'];
    $request = ClientUpdateDto::fromArray($user_id, $user_data);

    $request_type = $action === 'add'
            ? RequestTypes::NEW_CLIENT
            : RequestTypes::UPDATE_CLIENT;

    (new QueueRepository())->add($request_type, $user_id, $request->toArray());
}

function fn_maxma_sync_place_order_post($cart, $auth, $action, $issuer_id, $parent_order_id, $order_id)
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
}

function fn_maxma_sync_pre_update_order(&$cart)
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

function fn_maxma_sync_create_order(&$order)
{
    if (!empty($order['maxma_promotion_data'])) {
        $order['maxma_promotion_data'] = serialize($order['maxma_promotion_data']);
    }
}

function fn_maxma_sync_change_order_status_post($order_id, $status_to)
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

    if ($queue_type !== null) {
        (new QueueRepository())->add($queue_type, $order_id, ['orderId' => $order_id]);
    }
}

function fn_maxma_sync_get_user_info($user_id, $get_profile, $profile_id, &$user_data)
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

function fn_maxma_sync_queue()
{
    return __(
        'maxma_sync.process_queue',
        [
            '[process_queue_url]' => fn_url('maxma.process_queue', SiteArea::ADMIN_PANEL),
        ]
    );
}

function fn_maxma_sync_calculate_cart_content_before_shipping_calculation(&$cart, $auth)
{
    if (
        in_array(Registry::get('runtime.mode'), ['cart', 'checkout'])
    ) {
        $settings = Registry::get('addons.maxma_sync');
        $cart_service = new CartService($settings);

        $new_calculation = !empty($cart['maxma_promotion_data']['total_discount']) ? $cart['maxma_promotion_data'] : false;

        if (empty($new_calculation)) {
            $new_calculation = $cart_service->calculateCartContent($cart, $auth);
        }

        if ($new_calculation) {
            $new_calculation = is_array($new_calculation) ? $new_calculation : $cart_service->calculationToArray($new_calculation);

            if (
                !empty($new_calculation['total_discount'])
                && !empty($cart['maxma_promotion_data']['total_discount'])
            ) {
                $cart['has_coupons'] = true;
                $cart['coupons'][$cart['maxma_promotion_data']['coupon']] = 1;

            }
            $cart_service->applyExternalBonuses($new_calculation, $cart);
        }
    }
}

function fn_maxma_sync_pre_promotion_check_coupon($pending_coupon, &$cart)
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