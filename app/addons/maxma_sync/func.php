<?php

use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Addons\MaxmaSync\Service\QueueService;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Registry;
use Tygh\Enum\OrderStatuses;

function fn_maxma_sync_update_profile($action, $user_data)
{
    $user_id = $user_data['user_id'];
    $request = UsersService::prepareForUpdate($user_id, $user_data);

    $request_type = $action == 'add' ? RequestTypes::NEW_CLIENT : RequestTypes::UPDATE_CLIENT;

    QueueService::add($request_type, $user_id, $request);
}

function fn_maxma_sync_save_cart_content_post(&$cart, $user_id)
{
    if (!$user_id || !$cart) {
        return;
    }
    $settings = Registry::get('addons.maxma_sync');
    $cart_service = new CartService($settings);
    $new_calculation = $cart_service->calculateCartContent($cart, 0, 0);
    //fn_print_r($new_calculation);
}

function fn_maxma_sync_place_order_post($cart, $auth, $action, $issuer_id, $parent_order_id, $order_id)
{
    $payload = [
        'orderId' => $order_id,
        'userId' => $auth['user_id'],
        'items' => $cart['products'],
        'total' => $cart['total'],
        'discounts' => $cart['discounts'] ?? []
    ];

    QueueService::add(RequestTypes::SET_ORDER, $order_id, $payload);
}

function fn_maxma_sync_change_order_status_post($order_id, $status_to, $status_from, $force_notification, $place_order, $order_info)
{
    $queue_type = match ($status_to) {
        OrderStatuses::COMPLETE => RequestTypes::CONFIRM_ORDER,   // Подтвержден
        OrderStatuses::CANCELED => RequestTypes::CANCEL_ORDER,    // Отменён
        OrderStatuses::FAILED => RequestTypes::APPLY_RETURN,    // Возврат TODO нужен статус для возврата. Текущий FAILED не совсем подхиодит
        default => null
    };

    if ($queue_type) {
        $payload = [
            'orderId' => $order_id,
            'userId' => $order_info['user_id'],
        ];
        QueueService::add($queue_type, $order_id, $payload);
    }
}

function fn_maxma_sync_get_user_info($user_id, $get_profile, $profile_id, &$user_data)
{
    if (!$user_id || !$user_data['phone']) {
        return;
    }
    $settings = Registry::get('addons.maxma_sync');
    $user_service = new UsersService($settings);

    $user_data['balance'] = $user_service->getUserBalance($user_id, $user_data);
    $user_data['history'] = $user_service->getUserHistory($user_id, $user_data);
}

function fn_maxma_sync_update_cart_content($cart, $user_id)
{

}
