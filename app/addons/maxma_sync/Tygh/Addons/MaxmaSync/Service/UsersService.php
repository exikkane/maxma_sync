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

class UsersService
{
    public const BALANCE_CACHE_KEY = 'balance';
    public const HISTORY_CACHE_KEY = 'history';
    public function __construct(
        private readonly array           $settings,
        private array                    &$session = [],
        private ?MaxmaClient             $maxmaClient = null,
        private readonly QueueRepository $queue_repository = new QueueRepository(),
        private readonly UserRepository  $user_repository = new UserRepository(),
        private ?UserCache               $user_cache = null,
        private ?UserSession             $user_session = null,
        private readonly MaxmaLogger     $logger = new MaxmaLogger()
    ) {
        $this->maxmaClient = $this->maxmaClient ?? new MaxmaClient($this->settings);
        $this->user_session = $this->user_session ?? new UserSession($this->session);
        $this->user_cache = $this->user_cache ?? new UserCache($this->settings['maxma_cache_ttl']);
    }
    public function saveUserBonusesData(int $user_id, array $data, string $key): void
    {
        if (!empty($data['balance'])) {
            $this->user_repository->saveRewardPoints($user_id, $data['balance']);
        }

        $this->user_session->set($data, $key);
        $this->user_cache->set($user_id, $data, $key);
    }

    public function getUserBonusesData(int $user_id, array $user_data, string $key): array
    {
        $data = $this->user_session->get($key);

        if (empty($data)) {
            $data = $this->user_cache->get($user_id, $key);
            if (!empty($data)) {
                $this->user_session->set($data, $key);
            } else {
                $data = $this->fetchUserBonusesData($user_id, $user_data, $key);
                $this->saveUserBonusesData($user_id, $data, $key);
            }
        }
        return $data;
    }

    public function fetchUserBonusesData(int $user_id, array $user_data, string $key): array
    {
        try {
            $phone = !empty($user_data['phone'])
                ? $user_data['phone']
                : $this->user_repository->getUserPhone($user_id);

            $payload = new ClientDto(
                $phone,
                (string) $user_id
            );

            $method = match ($key) {
                self::BALANCE_CACHE_KEY => 'getBalance',
                self::HISTORY_CACHE_KEY => 'getBonusHistory',
                default => throw new \InvalidArgumentException("Unknown cache key $key"),
            };

            return $this->maxmaClient->$method($payload->toArray());
        } catch (ProcessingException $e) {
            if ($e->getCode() === ProcessingException::ERR_CLIENT_NOT_FOUND) {
                $request = (new ClientUpdateDto($user_id))::fromArray($user_id, $user_data);
                $this->queue_repository->add(RequestTypes::NEW_CLIENT, $user_id, $request->toArray());
                $this->saveUserBonusesData($user_id, [], $key);
            }

            $this->logger->error('An error was appeared while processing user.', [
                'user_data' => $user_data,
                'exception' => $e->getHint()
            ]);
            return [];
        }
    }
}
