<?php

use Tygh\Addons\MaxmaSync\Service\QueueService;

if ($mode == 'process_queue')
{
    QueueService::processQueue();
}