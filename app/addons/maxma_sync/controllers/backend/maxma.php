<?php

use Tygh\Addons\MaxmaSync\Service\QueueService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Registry;
use Tygh\Enum\UserTypes;
use Tygh\Enum\ObjectStatuses;

/**
 * @var string $mode
 */
$settings = Registry::get('addons.maxma_sync');
if ($mode === 'process_queue') {
    try {
        $queue_service = new QueueService($settings);
        $queue_service->processQueue();
       // $queue_service->clearProcessedQueue();
        fn_set_notification('N', __('notice'), __('maxma_sync.queue_synced'));
    } catch (\Exception $e) {

        fn_set_notification('E', __('error'), $e->getMessage());
        return;
    }
}

if ($mode === 'update_balances') {
    try {
        $user_service = new UsersService($settings);
        $users_data = db_get_array('SELECT user_id, phone FROM ?:users WHERE status = ?s AND user_type = ?s', ObjectStatuses::ACTIVE, UserTypes::CUSTOMER);

        $user_service->updateUserBalanceBulk($users_data);

        fn_set_notification('N', __('notice'), __('maxma_sync.balance_updated'));
    } catch (\Exception $e) {
        fn_set_notification('E', __('error'), $e->getMessage());
    }
}