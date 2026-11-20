<?php

namespace Tygh\Addons\MaxmaSync\Repository;

use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class QueueRepository
{
    /**
     * Добавляет новый элемент в очередь
     *
     * @param string $type Тип запроса (RequestTypes)
     * @param int $entityId ID сущности, к которой относится запрос
     * @param array<array-key, mixed> $payload Данные запроса
     * @return int ID созданной записи
     */
    public function add(string $type, int $entityId, array $payload): int
    {
        return (int) db_query('INSERT INTO ?:maxma_queue ?e', [
            'type'       => $type,
            'entity_id'  => $entityId,
            'payload'    => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'status'     => QueueStatuses::NEW,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    /**
     * Получает все элементы очереди со статусом NEW
     *
     * @return array<array-key, mixed> Массив элементов очереди
     */
    public function getPending(): array
    {
        return db_get_array(
            "SELECT * FROM ?:maxma_queue WHERE status = ?s ORDER BY created_at, id",
            QueueStatuses::NEW,
        );
    }

    /**
     * Получает ID элементов очереди со статусом DONE
     *
     * @return array<array-key, mixed> Массив ID обработанных элементов очереди
     */
    public function getProcessed(): array
    {
        return db_get_fields(
            "SELECT id FROM ?:maxma_queue WHERE status = ?s AND type NOT IN (?a)",
            QueueStatuses::DONE,
            [RequestTypes::APPLY_RETURN, RequestTypes::SET_ORDER, RequestTypes::CONFIRM_ORDER, RequestTypes::CANCEL_ORDER],
        );
    }

    /**
     * Удаляет элемент очереди по ID
     *
     * @param int $queue_id ID элемента очереди
     * @return void
     */
    public function deleteQueue(int $queue_id): void
    {
        db_query('DELETE FROM ?:maxma_queue WHERE id = ?i', $queue_id);
    }

    /**
     * Count errors and interrupted jobs that need manual reconciliation.
     */
    public function countUnresolvedFailures(): int
    {
        return (int) db_get_field(
            'SELECT COUNT(*) FROM ?:maxma_queue WHERE status IN (?a)',
            [QueueStatuses::ERROR, QueueStatuses::PROCESSING]
        );
    }

    /**
     * Only a worker that moved the job from NEW to PROCESSING may send it.
     */
    public function claim(int $id): bool
    {
        return (int) db_query(
            'UPDATE ?:maxma_queue SET ?u WHERE id = ?i AND status = ?s',
            ['status' => QueueStatuses::PROCESSING, 'updated_at' => time()],
            $id,
            QueueStatuses::NEW
        ) === 1;
    }

    /**
     * Read the most recent local outcome of an order operation.
     */
    public function latestStatus(string $type, int $order_id): ?string
    {
        $status = db_get_field(
            'SELECT status FROM ?:maxma_queue WHERE type = ?s AND entity_id = ?i ORDER BY id DESC LIMIT 1',
            $type,
            $order_id
        );
        return is_string($status) && $status !== '' ? $status : null;
    }

    /**
     * Read the latest terminal intent, including older pending transitions.
     *
     * @return array{id: int, type: string, status: string}|null
     */
    public function latestTerminalIntent(int $order_id): ?array
    {
        $row = db_get_row(
            'SELECT id, type, status FROM ?:maxma_queue WHERE entity_id = ?i AND type IN (?a)'
            . ' AND status <> ?s ORDER BY id DESC LIMIT 1',
            $order_id,
            [RequestTypes::CONFIRM_ORDER, RequestTypes::CANCEL_ORDER],
            QueueStatuses::SUPERSEDED
        );
        if (!isset($row['id'], $row['type'], $row['status'])) {
            return null;
        }
        return ['id' => (int) $row['id'], 'type' => (string) $row['type'], 'status' => (string) $row['status']];
    }

    /**
     * Find a terminal request with an outcome that did not permit another terminal operation.
     */
    public function hasOtherTerminalOutcome(int $order_id, int $id): bool
    {
        return (int) db_get_field(
            'SELECT COUNT(*) FROM ?:maxma_queue WHERE entity_id = ?i AND id <> ?i AND type IN (?a) AND status IN (?a)',
            $order_id,
            $id,
            [RequestTypes::CONFIRM_ORDER, RequestTypes::CANCEL_ORDER],
            [QueueStatuses::DONE, QueueStatuses::ERROR, QueueStatuses::PROCESSING]
        ) > 0;
    }

    /**
     * Check both current and legacy queue keys against the stable MAXMA transaction ID.
     */
    public function hasReturn(int $return_id, int $order_id, ?string $status = null): bool
    {
        $condition = $status === null ? '' : db_quote(' AND status = ?s', $status);
        $payloads = db_get_fields(
            'SELECT payload FROM ?:maxma_queue WHERE type = ?s AND entity_id IN (?n)' . $condition,
            RequestTypes::APPLY_RETURN,
            [$return_id, $order_id]
        );
        foreach ($payloads as $json) {
            $payload = json_decode((string) $json, true);
            if (is_array($payload) && (string) ($payload['id'] ?? '') === (string) $return_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Serialize competing enqueue or processing actions across store workers.
     */
    public function acquireLock(string $scope): bool
    {
        return (int) db_get_field(
            'SELECT GET_LOCK(SHA2(CONCAT(DATABASE(), ?s), 256), ?i)',
            ':maxma_sync:' . $scope,
            $scope === 'worker' ? 0 : 5
        ) === 1;
    }

    /**
     * Release a lock held by this connection.
     */
    public function releaseLock(string $scope): void
    {
        db_get_field('SELECT RELEASE_LOCK(SHA2(CONCAT(DATABASE(), ?s), 256))', ':maxma_sync:' . $scope);
    }

    /**
     * Обновляет статус элемента очереди
     *
     * @param int $id ID элемента очереди
     * @param string $status Новый статус (QueueStatuses)
     * @return void
     */
    public function updateStatus(int $id, string $status): void
    {
        db_query(
            "UPDATE ?:maxma_queue SET ?u WHERE id = ?i",
            [
                'status'     => $status,
                'updated_at' => time(),
            ],
            $id
        );
    }
}
