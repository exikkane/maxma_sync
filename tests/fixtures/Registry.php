<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace Tygh;

final class Registry
{
    private static array $data = [];

    public static function get(string $name): mixed
    {
        return self::$data[$name] ?? null;
    }

    public static function set(string $name, mixed $value): void
    {
        self::$data[$name] = $value;
    }

    public static function del(string $name): void
    {
        unset(self::$data[$name]);
    }
}
