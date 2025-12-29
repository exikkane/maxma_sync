<?php

namespace Tygh\Addons\MaxmaSync\Repository;

class UserRepository
{
    public function get(int $user_id, string $key): ?array
    {
        return db_get_row(
            "SELECT $key, updated_at FROM ?:maxma_user_cache WHERE user_id = ?i",
            $user_id
        ) ?: null;
    }

    public function save(int $user_id, array $data, string $key): void
    {
        db_replace_into('maxma_user_cache', [
            'user_id'    => $user_id,
             $key        => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => time(),
        ]);
    }

    public function saveRewardPoints(int $user_id, int $amount): void
    {
        fn_save_user_additional_data(POINTS, $amount, $user_id);

        db_query(
            "REPLACE INTO ?:reward_point_changes ?e",
            [
                'user_id'  => $user_id,
                'amount'   => $amount,
                'timestamp'=> time(),
                'action'   => '',
                'reason'   => 'Maxma Sync',
            ]
        );
    }
    public function getUserPhone(int $user_id): ?string
    {
        return db_get_field('SELECT phone FROM ?:users WHERE user_id = ?i', $user_id);
    }
}