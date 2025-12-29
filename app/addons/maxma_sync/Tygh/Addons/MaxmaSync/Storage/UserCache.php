<?php

namespace Tygh\Addons\MaxmaSync\Storage;

use Tygh\Addons\MaxmaSync\Repository\UserRepository;

class UserCache
{
    public function __construct(
        private readonly int            $ttl,
        private readonly UserRepository $userRepository = new UserRepository(),
    ) {}

    public function get(int $user_id, string $key)
    {
        $data = $this->userRepository->get($user_id, $key);

        if (
            !$data
            || empty($data[$key])
            || !$this->isCacheValid((int) $data['updated_at'])
        ) {
            return [];
        }

        return json_decode($data[$key], true, 512, JSON_THROW_ON_ERROR);
    }
    public function set(int $user_id, array $data, string $key): void
    {
        $this->userRepository->save($user_id, $data, $key);
    }
    private function isCacheValid(?int $updated_at): bool
    {
        return $updated_at && (TIME - $updated_at < $this->ttl);
    }
}