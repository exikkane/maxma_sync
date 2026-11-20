<?php

namespace Tygh\Addons\MaxmaSync\Storage;

use Tygh\Addons\MaxmaSync\Repository\UserRepository;

class UserCache
{
    public function __construct(
        private readonly int            $ttl,
        private readonly UserRepository $userRepository = new UserRepository(),
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function get(int $user_id, string $key): array
    {
        $data = $this->userRepository->get($user_id, $key);

        if (
            $data === null
            || !isset($data[$key])
            || !is_string($data[$key])
            || $data[$key] === ''
            || !$this->isCacheValid((int) $data['updated_at'])
        ) {
            return [];
        }

        try {
            $data = json_decode($data[$key], true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : [];
        } catch (\JsonException $e) {
            return [];
        }
    }
    /**
     * @param array<array-key, mixed> $data
     */
    public function set(int $user_id, array $data, string $key): void
    {
        $this->userRepository->save($user_id, $data, $key);
    }
    private function isCacheValid(?int $updated_at): bool
    {
        return $updated_at !== null && $updated_at > 0 && TIME - $updated_at < $this->ttl;
    }
}
