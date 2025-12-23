<?php

namespace Tygh\Addons\MaxmaSync\Service;

use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Registry;
use CloudLoyalty\Api\Exception\ProcessingException;

class UsersService
{
    private array $settings;
    private int $cache_ttl;

    public function __construct(array $settings) {
        $this->cache_ttl = $settings['maxma_cache_ttl'];
        $this->settings = $settings;
    }

    public function getUserBalance(int $user_id, array $user_data): array
    {
        $maxma_user_balance = db_get_row(
            "SELECT balance, balance_updated_at FROM ?:maxma_user_cache WHERE user_id = ?i",
            $user_id
        );

        if ($maxma_user_balance && $this->isCacheValid((int) $maxma_user_balance['balance_updated_at'])) {
            return $maxma_user_balance['balance'];
        }
        $client = MaxmaClient::getInstance($this->settings);
        try {
            $payload = [
                'externalId' => $user_id,
                'phoneNumber' => $user_data['phone'],
            ];
            $balance = $client->getBalance($payload);
            self::saveUserBalance($user_id, $balance);

            return $balance;
        } catch (ProcessingException $e) {
            if ($maxma_user_balance && $maxma_user_balance['balance']) {
                return $maxma_user_balance;
            }

            if (str_contains($e->getMessage(), 'Клиент не найден')) {
                $request = self::prepareForUpdate($user_id, $user_data);
                QueueService::add(RequestTypes::NEW_CLIENT, $user_id, $request);
            }
            return [];
        }
    }

    public static function saveUserBalance(int $user_id, array $balance): void
    {
        db_replace_into('maxma_user_cache', [
            'user_id' => $user_id,
            'balance' => json_encode($balance, JSON_UNESCAPED_UNICODE),
            'updated_at' => TIME,
        ]);
    }

    public function getUserHistory(int $user_id, array $user_data): array
    {
        $maxma_user_history = db_get_row(
            "SELECT history, history_updated_at FROM ?:maxma_user_cache WHERE user_id = ?i",
            $user_id
        );

        if ($maxma_user_history && $this->isCacheValid((int) $maxma_user_history['history_updated_at'])) {
            return $maxma_user_history['history'];
        }
        $client = MaxmaClient::getInstance($this->settings);
        try {
            $payload = [
                'externalId' => $user_id,
                'phoneNumber' => $user_data['phone'],
            ];
            $history = $client->getBonusHistory($payload);
            self::saveUserHistory($user_id, $history);

            return $history;
        } catch (ProcessingException $e) {
            if ($maxma_user_history && $maxma_user_history['history']) {
                return json_decode($maxma_user_history['history'], true);
            }

            if (str_contains($e->getMessage(), 'Клиент не найден')) {
                $request = self::prepareForUpdate($user_id, $user_data);
                QueueService::add(RequestTypes::NEW_CLIENT, $user_id, $request);
            }

            return [];
        }

    }

    public static function saveUserHistory(int $user_id, array $history): void
    {
        db_replace_into('maxma_user_cache',[
            'user_id' => $user_id,
            'history' => json_encode($history, JSON_UNESCAPED_UNICODE),
            'updated_at' => TIME,
        ]);
    }
    private function isCacheValid(?int $updated_at): bool
    {
        return $updated_at && (TIME - $updated_at < $this->cache_ttl);
    }

    public static function prepareForUpdate(int $user_id, array $user_data): array
    {
        return [
            'client' => [
                'user_id' => $user_id,
                'email' => $user_data['email'] ?? '',
                'phoneNumber' => $user_data['phone'] ?? '',
                'name' => $user_data['firstname'] ?? '',
                'surname' => $user_data['lastname'] ?? ''
            ]
        ];
    }
}