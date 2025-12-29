<?php

use Tygh\Addons\MaxmaSync\Service\QueueService;
use Tygh\Registry;
/**
 * @var string $mode
 */
if ($mode === 'process_queue')
{
    $settings = Registry::get('addons.maxma_sync');
    $queue_service = new QueueService($settings);
    $queue_service->processQueue();
}