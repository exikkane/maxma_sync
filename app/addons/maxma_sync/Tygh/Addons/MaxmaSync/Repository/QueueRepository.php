<?php

namespace Tygh\Addons\MaxmaSync\Repository;

use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;

class QueueRepository
{
    /**
     * Добавляет элемент в очередь
     *
     * @param string $type Тип запроса (RequestTypes)
     * @param int $entityId ID сущности, к которой относится запрос
     * @param array $payload Данные запроса
     * @return int ID созданной или обновленной записи
     */
    public function add(string $type, int $entityId, array $payload): int
    {
        return db_replace_into('maxma_queue', [
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
     * @return array Массив элементов очереди
     */
    public function getPending(): array
    {
        return db_get_array(
            "SELECT * FROM ?:maxma_queue WHERE status = ?s ORDER BY created_at",
            QueueStatuses::NEW,
        );
    }

    /**
     * Получает ID элементов очереди со статусом DONE
     *
     * @return array Массив ID обработанных элементов очереди
     */
    public function getProcessed(): array
    {
        return db_get_fields(
            "SELECT id FROM ?:maxma_queue WHERE status = ?s",
            QueueStatuses::DONE,
        );
    }

    /**
     * Удаляет элемент очереди по ID
     *
     * @param int $queue_id ID элемента очереди
     * @return void
     */
    public function deleteQueue($queue_id): void
    {
        db_query('DELETE FROM ?:maxma_queue WHERE id = ?i', $queue_id);
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
