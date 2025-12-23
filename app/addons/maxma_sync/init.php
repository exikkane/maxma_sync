<?php

defined('BOOTSTRAP') or die('Access denied');

require_once __DIR__ . '/lib/autoload.php';

fn_register_hooks('update_profile', 'get_user_info', 'save_cart_content_post', 'place_order_post', 'change_order_status_post');