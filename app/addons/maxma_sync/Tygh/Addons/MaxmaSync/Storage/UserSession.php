<?php

namespace Tygh\Addons\MaxmaSync\Storage;

class UserSession
{
    public function __construct(
        private array &$session,
    ) {}

    public function get(string $key): array
    {
        $data = $this->session[$key] ?? [];

        if (
            empty($data)
            || !isset($data['updated_at'])
            || TIME - $data['updated_at'] > 120 // FIXME 2 минуты в сессии валиден кеш
        ) {
            return [];
        }

        return $data;
    }

    public function set(array $data, string $key): void
    {
        $data['updated_at'] = TIME;
        $this->session[$key] = $data;

    }
}