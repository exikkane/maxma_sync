<?php

namespace Tygh\Addons\MaxmaSync\Repository;

use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;

class QueueRepository
{
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

    public function getPending(int $limit = 20): array
    {
        return db_get_array(
            "SELECT * FROM ?:maxma_queue WHERE status = ?s ORDER BY created_at ASC LIMIT ?i",
            QueueStatuses::NEW,
            $limit
        );
    }

    public function updateStatus(
        int $id,
        string $status,
        string $errorMessage = ''
    ): void {
        db_query(
            "UPDATE ?:maxma_queue SET ?u WHERE id = ?i",
            [
                'status'        => $status,
                'error_message' => $errorMessage,
                'updated_at'    => time(),
            ],
            $id
        );
    }
}
