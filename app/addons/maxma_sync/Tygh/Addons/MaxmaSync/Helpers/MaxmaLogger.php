<?php

namespace Tygh\Addons\MaxmaSync\Helpers;

class MaxmaLogger
{
    private string $logFile;

    public function __construct(string $logFile = DIR_ROOT . '/var/maxma_sync.log')
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

        $line = sprintf("[%s] [%s] %s", $date, $level, $message) . PHP_EOL;

        if (!empty($context)) {
            $line .= "Context:" . PHP_EOL;
            $line .= json_encode($this->normalizeContext($context), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        $line .= str_repeat('-', 80) . PHP_EOL;

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function normalizeContext(array $context): array
    {
        array_walk_recursive($context, function (&$value) {
            if (is_string($value) && self::isJson($value)) {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            }
        });

        return $context;
    }

    private static function isJson(string $string): bool
    {
        if ($string === '' || $string === 'null') {
            return false;
        }

        try {
            json_decode($string, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException $e) {
            return false;
        }
    }
}
