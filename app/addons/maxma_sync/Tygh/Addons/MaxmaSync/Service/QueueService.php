<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Exception\TransportException;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;

class QueueService
{
    private readonly MaxmaClient $client;
    /**
     * @param array<array-key, mixed> $settings Настройки модуля
     * @param MaxmaClient|null $client Клиент для работы с Maxma API
     * @param QueueRepository $repository Репозиторий очереди
     * @param MaxmaLogger $logger Логгер
     */
    public function __construct(
        private readonly array $settings,
        ?MaxmaClient $client = null,
        private readonly QueueRepository $repository = new QueueRepository(),
        private readonly MaxmaLogger $logger = new MaxmaLogger()
    )
    {
        $this->client = $client ?? new MaxmaClient($this->settings);
    }

    /**
     * Обработка всей очереди
     *
     * @return void
     */
    public function processQueue(): void
    {
        if (!$this->repository->acquireLock('worker')) {
            throw new RuntimeException(__('maxma_sync.queue_busy'));
        }
        try {
            try {
                $this->processPending();
            } finally {
                $this->clearProcessedQueue();
            }
        } finally {
            $this->repository->releaseLock('worker');
        }
    }

    private function processPending(): void
    {
        $items = $this->repository->getPending();
        usort($items, static function (array $left, array $right): int {
            return self::priority($left['type']) <=> self::priority($right['type']);
        });

        $failed = 0;
        foreach ($items as $item) {
            if (!$this->processItem($item)) {
                ++$failed;
            }
        }
        if ($failed > 0) {
            throw new RuntimeException(__('maxma_sync.queue_failed', ['[count]' => $failed]));
        }
        if ($this->repository->countUnresolvedFailures() > 0) {
            throw new RuntimeException(__('maxma_sync.queue_unresolved'));
        }
    }

    private static function priority(string $type): int
    {
        return match ($type) {
            RequestTypes::NEW_CLIENT, RequestTypes::UPDATE_CLIENT => 0,
            RequestTypes::SET_ORDER => 1,
            RequestTypes::CONFIRM_ORDER, RequestTypes::CANCEL_ORDER => 2,
            default => 3,
        };
    }

    /**
     * Handle one job and keep failures visible to the caller.
     * @param array<array-key, mixed> $item
     */
    private function processItem(array $item): bool
    {
        $id = (int) $item['id'];
        $method = $item['type'];
        if (!$this->repository->claim($id)) {
            return true;
        }

        try {
            $payload = json_decode($item['payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !$payload || !in_array($method, RequestTypes::getQueueTypes(), true)) {
                throw new InvalidArgumentException('Invalid MAXMA queue job.');
            }

            if (in_array($method, [RequestTypes::CONFIRM_ORDER, RequestTypes::CANCEL_ORDER], true)) {
                $order_id = (int) ($payload['orderId'] ?? 0);
                $intent = $this->repository->latestTerminalIntent($order_id);
                if ($intent !== null && $intent['id'] > $id) {
                    $this->repository->updateStatus($id, QueueStatuses::SUPERSEDED);
                    return true;
                }
            }

            if ($this->isOrderOperationBlocked($id, $method, $payload)) {
                $this->repository->updateStatus($id, QueueStatuses::NEW);
                $this->logger->info('MAXMA order job is waiting for a confirmed prerequisite.', [
                    'queue_id' => $id,
                    'type' => $method,
                ]);
                return false;
            }

            $already_returned = false;
            if ($method === RequestTypes::APPLY_RETURN) {
                $return_id = (int) ($payload['id'] ?? 0);
                if ($return_id <= 0) {
                    throw new InvalidArgumentException('Missing MAXMA return ID.');
                }
                $already_returned = $this->repository->hasReturn($return_id, (int) $payload['purchaseId'], QueueStatuses::DONE);
            }
            if (!$already_returned) {
                try {
                    $this->client->$method($payload);
                } catch (ProcessingException $e) {
                    if ($method !== RequestTypes::NEW_CLIENT || $e->getCode() !== ProcessingException::ERR_DUPLICATING_PHONE) {
                        throw $e;
                    }
                    $this->client->updateClient($payload);
                }
            }

            $this->repository->updateStatus($id, QueueStatuses::DONE);
            return true;
        } catch (ProcessingException | TransportException | InvalidArgumentException | JsonException | \TypeError $e) {
            $this->repository->updateStatus($id, QueueStatuses::ERROR);
            $this->logger->error('MAXMA queue job failed.', [
                'queue_id' => $id,
                'type' => $method,
                'error' => get_class($e),
                'code' => $e->getCode(),
            ]);
            return false;
        }
    }

    /**
     * Defer local dependent jobs while their prerequisites are unresolved.
     * @param array<array-key, mixed> $payload
     */
    private function isOrderOperationBlocked(int $id, string $method, array $payload): bool
    {
        if (!in_array($method, [RequestTypes::CONFIRM_ORDER, RequestTypes::CANCEL_ORDER, RequestTypes::APPLY_RETURN], true)) {
            return false;
        }
        $order_id = (int) ($payload[$method === RequestTypes::APPLY_RETURN ? 'purchaseId' : 'orderId'] ?? 0);
        if ($order_id <= 0) {
            throw new InvalidArgumentException('Missing MAXMA order ID.');
        }
        $set = $this->repository->latestStatus(RequestTypes::SET_ORDER, $order_id);
        if ($method === RequestTypes::APPLY_RETURN) {
            return ($set !== null && $set !== QueueStatuses::DONE)
                || $this->repository->latestStatus(RequestTypes::CONFIRM_ORDER, $order_id) !== QueueStatuses::DONE;
        }
        return $set !== QueueStatuses::DONE || $this->repository->hasOtherTerminalOutcome($order_id, $id);
    }

    /**
     * Очистка всех обработанных элементов очереди
     *
     * @return void
     */
    public function clearProcessedQueue(): void
    {
        $items = $this->repository->getProcessed();
        if (!$items) {
            return;
        }
        foreach ($items as $item) {
            $this->repository->deleteQueue((int) $item);
        }
    }
}
