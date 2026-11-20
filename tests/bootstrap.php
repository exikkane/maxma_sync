<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

define('DIR_ROOT', dirname(__DIR__));
define('TIME', time());

$addon = DIR_ROOT . '/app/addons/maxma_sync';
require $addon . '/lib/vendor/autoload.php';
require __DIR__ . '/stubs/cscart-lock.php';
spl_autoload_register(static function (string $class) use ($addon): void {
    if (strpos($class, 'Tygh\\') !== 0) {
        return;
    }
    $file = $addon . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function __(string $key, array $params = []): string
{
    return strtr($key, $params);
}

function fn_format_price(float $amount): float
{
    return round($amount, 2);
}

function fn_set_notification(string $type, string $title, string $message): void
{
}
