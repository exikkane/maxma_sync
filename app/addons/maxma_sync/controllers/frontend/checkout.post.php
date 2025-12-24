<?php

use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Registry;

$points_to_use = empty($_REQUEST['points_to_use']) ? 0 : intval($_REQUEST['points_to_use']);

if (
    $mode == 'delete_coupon'
    || $mode == 'apply_coupon'
    || $mode == 'point_payment'
    || $mode == 'cart'
    || $mode == 'checkout'
) {
    $cart = & Tygh::$app['session']['cart'];
    $auth = Tygh::$app['session']['auth'];

    if (!$auth['user_id'] || !$cart) {
        return;
    }
    $promo_code = $_REQUEST['coupon_code'] ?? '';
    $reward_points = $cart['reward_points']['points_info']['in_use']['points'] ?? 0;

    $settings = Registry::get('addons.maxma_sync');
    $cart_service = new CartService($settings);
    $new_calculation = $cart_service->calculateCartContent($cart, 0, $promo_code);

    $user_service = new UsersService($settings);
    $user_reward_balance = $user_service->getUserBalance($auth['user_id'], $cart['user_data'], true);
    $user_service::saveUserBalance($auth['user_id'], $user_reward_balance);
}