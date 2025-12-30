<?php

namespace Tygh\Addons\MaxmaSync\Repository;

class UserRepository
{
    /**
     * Получает данные пользователя из кэша Maxma
     *
     * @param int $user_id ID пользователя
     * @param string $key Ключ данных в кэше
     * @return array|null Ассоциативный массив с данными и updated_at, или null если нет записи
     */
    public function get(int $user_id, string $key): ?array
    {
        return db_get_row(
            "SELECT $key, updated_at FROM ?:maxma_user_cache WHERE user_id = ?i",
            $user_id
        ) ?: null;
    }

    /**
     * Сохраняет данные пользователя в кэш Maxma
     *
     * @param int $user_id ID пользователя
     * @param array $data Данные для сохранения
     * @param string $key Ключ данных в кэше
     * @return void
     */
    public function save(int $user_id, array $data, string $key): void
    {
        db_replace_into('maxma_user_cache', [
            'user_id'    => $user_id,
            $key         => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => time(),
        ]);
    }

    /**
     * Сохраняет количество бонусных баллов пользователя и фиксирует изменение
     *
     * @param int $user_id ID пользователя
     * @param int $amount Количество баллов
     * @return void
     */
    public function saveRewardPoints(int $user_id, int $amount): void
    {
        fn_save_user_additional_data(POINTS, $amount, $user_id);

        db_query(
            "REPLACE INTO ?:reward_point_changes ?e",
            [
                'user_id'   => $user_id,
                'amount'    => $amount,
                'timestamp' => time(),
                'action'    => '',
                'reason'    => 'Maxma Sync',
            ]
        );
    }

    /**
     * Получает телефон пользователя по его ID
     *
     * @param int $user_id ID пользователя
     * @return string|null Телефон пользователя или null, если не найден
     */
    public function getUserPhone(int $user_id): ?string
    {
        return db_get_field('SELECT phone FROM ?:users WHERE user_id = ?i', $user_id);
    }
}
