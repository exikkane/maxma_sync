<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Registry;
use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;

class QueueService
{
    /**
     * Добавить элемент в очередь
     */
    public static function add(string $type, int $entity_id, array $payload): int
    {
        if (!RequestTypes::isValid($type)) {
            throw new \InvalidArgumentException("Invalid request type: {$type}");
        }

        $data = [
            'type' => $type,
            'entity_id' => $entity_id,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => QueueStatuses::NEW,
            'created_at' => TIME,
            'updated_at' => TIME,
        ];

        return db_query("INSERT INTO ?:maxma_queue ?e", $data);
    }

    /**
     * Получить элементы из очереди для обработки
     */
    public static function getPending(int $limit = 20): array
    {
        return db_get_array(
            "SELECT * FROM ?:maxma_queue WHERE status = ?s ORDER BY created_at ASC LIMIT ?i",
            QueueStatuses::NEW,
            $limit
        );
    }

    /**
     * Обновить статус элемента очереди
     */
    public static function updateStatus(int $id, string $status, string $error_message = ''): void
    {
        $data = [
            'status' => $status,
            'error_message' => $error_message,
            'updated_at' => TIME,
        ];
        db_query("UPDATE ?:maxma_queue SET ?u WHERE id = ?i", $data, $id);
    }

    public static function processQueue(): void
    {
        $items = self::getPending();
        if (!$items) {
            return;
        }

        $settings = Registry::get('addons.maxma_sync');
        $client   = MaxmaClient::getInstance($settings);
        $logger   = new MaxmaLogger();

        foreach ($items as $item) {
            self::processItem($item, $client, $logger);
        }
    }

    private static function processItem(array $item, $client, MaxmaLogger $logger = new MaxmaLogger()): void
    {
        try {
            self::updateStatus($item['id'], QueueStatuses::PROCESSING);

            $method  = $item['type'];
            $payload = json_decode($item['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (!RequestTypes::isValid($method)) {
                throw new \InvalidArgumentException("Invalid request type: {$method}");
            }

            try {
                $client->$method($payload);
            } catch (ProcessingException $e) {
                if (
                    $method === RequestTypes::NEW_CLIENT
                    && str_contains($e->getMessage(), 'уже существует')
                ) {
                    $client->updateClient($payload);
                } else {
                    self::updateStatus($item['id'], QueueStatuses::ERROR, $e->getMessage());
                    $logger->error(
                        "Queue item failed: ID {$item['id']}",
                        ['exception' => $e->getHint()]
                    );
                }
            }
            self::updateStatus($item['id'], QueueStatuses::DONE);
        } catch (ProcessingException $e) {
            self::updateStatus($item['id'], QueueStatuses::ERROR, $e->getMessage());

            $logger->error(
                "Queue item failed: ID {$item['id']}",
                ['exception' => $e->getHint()]
            );
        }
    }
}
