<?php

use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Registry;

/**
 * @var string $mode
 */
$cart = &Tygh::$app['session']['cart'];
$auth = Tygh::$app['session']['auth'];

$settings = Registry::get('addons.maxma_sync');
if (
    in_array($mode,
        ['point_payment', 'cart', 'checkout'])
) {
    if (!$auth['user_id'] || !$cart) {
        return;
    }
    $user_service = new UsersService($settings, $auth);
    $balance = $user_service->getUserBonusesData($auth['user_id'], $auth, $user_service::BALANCE_CACHE_KEY);

    Tygh::$app['view']->assign('balance', $balance);
}
if (
    $mode === 'delete_coupon'
) {
    unset($cart['maxma_promotion_data']);
}