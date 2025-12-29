<?php

defined('BOOTSTRAP') or die('Access denied');

require_once __DIR__ . '/lib/autoload.php';

fn_register_hooks('update_profile', 'get_user_info', 'place_order_post', 'change_order_status_post','calculate_cart_content_before_shipping_calculation', 'pre_promotion_check_coupon', 'pre_update_order', 'create_order');