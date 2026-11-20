<?php

use Tygh\Tygh;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Addons\MaxmaSync\Service\CartService;
use Tygh\Registry;

/**
 * @var string $mode
 */
$session = Tygh::$app['session'];
$cart = (array) ($session['cart'] ?? []);
$auth = (array) ($session['auth'] ?? []);

$settings = (array) Registry::get('addons.maxma_sync');
if (
    in_array($mode,
        ['point_payment', 'cart', 'checkout'], true)
) {
    $user_id = (int) ($auth['user_id'] ?? 0);
    if ($user_id <= 0 || !$cart) {
        return;
    }
    $cache = (array) ($session['maxma_sync_cache'] ?? []);
    $user_service = new UsersService($settings, $cache);
    $balance = $user_service->getUserBonusesData($user_id, $auth, $user_service::BALANCE_CACHE_KEY);

    $session['maxma_sync_cache'] = $cache;
    Tygh::$app['view']->assign('balance', $balance);
}
if ($mode === 'delete_coupon'
    && isset($cart['maxma_promotion_data']['coupon'])
    && $cart['maxma_promotion_data']['coupon'] === ($_REQUEST['coupon_code'] ?? null)
) {
    CartService::clearExternalBonuses($cart);
    CartService::clearCoupon($cart);
    $session['cart'] = $cart;
}
