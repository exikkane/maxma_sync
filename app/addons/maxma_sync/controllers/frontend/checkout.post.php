<?php

if (
    $mode == 'delete_coupon'
    || $mode == 'apply_coupon'
//    || $mode == 'cart'
//    || $mode == 'checkout'
) {
    $cart = Tygh::$app['session']['cart'];
    $auth = Tygh::$app['session']['auth'];

    $promo_code = $_REQUEST['coupon_code'] ?? '';
}