<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

class MaxmaLogger
{
    private string $logFile;

    public function __construct(string $logFile = DIR_ROOT . '/var/log/maxma_sync.log')
    {

    }

    /**
     * Логирование ошибки
     */
    public function error(string $message, array $context = []): void
    {

    }

    /**
     * Логирование информационных сообщений
     */
    public function info(string $message, array $context = []): void
    {

    }
}
