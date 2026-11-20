<?php

use Tygh\Tygh;
use Tygh\Addons\MaxmaSync\ServiceProvider;

defined('BOOTSTRAP') or die('Access denied');

require_once __DIR__ . '/lib/vendor/autoload.php';

Tygh::$app->register(new ServiceProvider());

fn_register_hooks('update_profile', 'get_user_info', 'place_order_post', 'change_order_status_post','calculate_cart', 'calculate_cart_items', 'pre_update_order', 'pre_place_order', 'create_order', 'update_order', 'form_cart');
