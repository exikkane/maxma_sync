<?php

namespace Tygh\Addons\MaxmaSync\Service;

use Tygh\Addons\MaxmaSync\Dto\ClientDto;
use Tygh\Addons\MaxmaSync\Dto\ClientUpdateDto;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;
use Tygh\Addons\MaxmaSync\Storage\UserCache;
use Tygh\Addons\MaxmaSync\Storage\UserSession;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Repository\UserRepository;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Exception\TransportException;

class UsersService
{
    private readonly UserCache $user_cache;
    private readonly UserSession $user_session;
    private readonly MaxmaClient $maxmaClient;
    public const BALANCE_CACHE_KEY = 'balance';
    public const HISTORY_CACHE_KEY = 'history';

    /**
     * @param array<array-key, mixed> $settings Настройки модуля
     * @param array<array-key, mixed> $session Сессия пользователя
     * @param MaxmaClient|null $maxmaClient Клиент для работы с Maxma API
     * @param QueueRepository $queue_repository Репозиторий очереди
     * @param UserRepository $user_repository Репозиторий пользователей
     * @param UserCache|null $user_cache Кеш пользователя
     * @param UserSession|null $user_session Сессия пользователя
     * @param MaxmaLogger $logger Логгер
     */
    public function __construct(
        private readonly array           $settings,
        private array                    &$session = [],
        ?MaxmaClient $maxmaClient = null,
        private readonly QueueRepository $queue_repository = new QueueRepository(),
        private readonly UserRepository  $user_repository = new UserRepository(),
        ?UserCache $user_cache = null,
        ?UserSession $user_session = null,
        private readonly MaxmaLogger     $logger = new MaxmaLogger()
    ) {
        $this->maxmaClient = $maxmaClient ?? new MaxmaClient($this->settings);
        $this->user_session = $user_session ?? new UserSession($this->session, (int) ($this->settings['maxma_cache_ttl'] ?? 120));
        $this->user_cache = $user_cache ?? new UserCache((int) ($this->settings['maxma_cache_ttl'] ?? 120));
    }

    /**
     * Сохраняет данные бонусов пользователя в репозиторий, сессию и кеш
     *
     * @param int $user_id
     * @param array<array-key, mixed> $data
     * @param string $key
     */
    public function saveUserBonusesData(int $user_id, array $data, string $key): void
    {
        if (!$data) {
            return;
        }
        if ($key === self::BALANCE_CACHE_KEY && isset($data['balance']) && is_int($data['balance'])) {
            $this->user_repository->saveRewardPoints($user_id, $data['balance']);
        }

        $this->user_session->set($user_id, $data, $key);
        $this->user_cache->set($user_id, $data, $key);
    }

    /**
     * Получает данные бонусов пользователя из сессии, кеша или API
     *
     * @param int $user_id
     * @param array<array-key, mixed> $user_data
     * @param string $key
     * @return array<array-key, mixed>
     */
    public function getUserBonusesData(int $user_id, array $user_data, string $key): array
    {
        $data = $this->user_session->get($user_id, $key);

        if (empty($data)) {
            $data = $this->user_cache->get($user_id, $key);
            if (!empty($data)) {
                $this->user_session->set($user_id, $data, $key);
            } else {
                $data = $this->fetchUserBonusesData($user_id, $user_data, $key);
                $this->saveUserBonusesData($user_id, $data, $key);
            }
        }
        return $data;
    }

    /**
     * Запрашивает данные бонусов пользователя из Maxma API
     *
     * @param int $user_id
     * @param array<array-key, mixed> $user_data
     * @param string $key
     * @return array<array-key, mixed>
     */
    public function fetchUserBonusesData(int $user_id, array $user_data, string $key): array
    {
        try {
            $phone = !empty($user_data['phone'])
                ? $user_data['phone']
                : $this->user_repository->getUserPhone($user_id);

            if ($phone === null || trim($phone) === '') {
                return [];
            }
            $payload = new ClientDto($phone, (string) $user_id);

            $method = match ($key) {
                self::BALANCE_CACHE_KEY => 'getBalance',
                self::HISTORY_CACHE_KEY => 'getBonusHistory',
                default => throw new \InvalidArgumentException("Unknown cache key $key"),
            };

            return $this->maxmaClient->$method($payload->toArray());
        } catch (ProcessingException | TransportException $e) {
            if ($e instanceof ProcessingException && $e->getCode() === ProcessingException::ERR_CLIENT_NOT_FOUND) {
                $user_data['phone'] = $phone ?? '';
                $this->queueNewClient($user_id, $user_data);
            }
            $this->logger->error('MAXMA user data request failed.', ['user_id' => $user_id, 'code' => $e->getCode()]);
            return [];
        }
    }

    /**
     * Ставит нового клиента в очередь для добавления
     *
     * @param int $user_id
     * @param array<array-key, mixed> $user_data
     */
    public function queueNewClient(int $user_id, array $user_data): void
    {
        if ($user_id <= 0 || trim($user_data['phone'] ?? '') === '') {
            return;
        }
        $request = ClientUpdateDto::fromArray($user_id, $user_data, [
            'shopCode' => $this->settings['maxma_shop_code'] ?? '',
            'shopName' => $this->settings['maxma_shop_name'] ?? '',
        ]);
        $this->queue_repository->add(RequestTypes::NEW_CLIENT, $user_id, $request->toArray());
    }

    /**
     * Массовое обновление баланса и истории пользователей
     *
     * @param array<array-key, mixed> $users_data
     */
    public function updateUserBalanceBulk(array $users_data): void
    {
        if (empty($users_data)) {
            return;
        }

        $keys = [self::BALANCE_CACHE_KEY, self::HISTORY_CACHE_KEY];

        foreach ($keys as $key) {
            foreach ($users_data as $user_data) {
                $data = $this->fetchUserBonusesData($user_data['user_id'], $user_data, $key);
                $this->saveUserBonusesData($user_data['user_id'], $data, $key);
            }
        }
    }
}
