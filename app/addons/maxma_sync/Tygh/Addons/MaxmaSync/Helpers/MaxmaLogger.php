<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

class MaxmaLogger
{
    private string $logFile;

    public function __construct(string $logFile = DIR_ROOT . '/var/log/maxma_sync.log')
    {
        $this->logFile = $logFile;
    }

    /**
     * Логирование ошибки
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * Логирование информационных сообщений
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * Общий метод записи
     */
    private function write(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');

        $contextString = '';
        if (!empty($context)) {
            $contextString = ' | context: ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = sprintf(
            "[%s] [%s] %s%s%s",
            $date,
            $level,
            $message,
            $contextString,
            PHP_EOL
        );

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
