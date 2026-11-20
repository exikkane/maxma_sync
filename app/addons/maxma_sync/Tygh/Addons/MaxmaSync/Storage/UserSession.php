<?php

namespace Tygh\Addons\MaxmaSync\Storage;

class UserSession
{
    /**
     * @param array<array-key, mixed> $session
     */
    public function __construct(
        private array &$session,
        private readonly int $ttl = 120,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function get(int $user_id, string $key): array
    {
        $data = $this->session['maxma_sync'][$user_id][$key] ?? [];

        if (
            !is_array($data)
            || !is_int($data['updated_at'] ?? null)
            || TIME - $data['updated_at'] >= $this->ttl
        ) {
            return [];
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function set(int $user_id, array $data, string $key): void
    {
        $data['updated_at'] = TIME;
        $this->session['maxma_sync'][$user_id][$key] = $data;

    }
}
