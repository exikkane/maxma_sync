<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;

class QueueService
{
    /**
     * @param array $settings Настройки модуля
     * @param MaxmaClient|null $client Клиент для работы с Maxma API
     * @param QueueRepository $repository Репозиторий очереди
     * @param MaxmaLogger $logger Логгер
     */
    public function __construct(
        private readonly array $settings,
        private ?MaxmaClient   $client = null,
        private readonly QueueRepository $repository = new QueueRepository(),
        private readonly MaxmaLogger $logger = new MaxmaLogger()
    )
    {
        $this->client = $this->client ?? new MaxmaClient($this->settings);
    }

    /**
     * Обработка всей очереди
     *
     * @return void
     */
    public function processQueue(): void
    {
        $items = $this->repository->getPending();
        if (!$items) {
            return;
        }
        foreach ($items as $item) {
            $this->processItem($item);
        }
    }

    /**
     * Обработка одного элемента очереди
     *
     * @param array $item Элемент очереди
     * @return void
     */
    private function processItem(array $item): void
    {
        try {
            $this->repository->updateStatus($item['id'], QueueStatuses::PROCESSING);

            $method  = $item['type'];
            $payload = json_decode($item['payload'], true);

            // Проверка валидности типа запроса
            if (!RequestTypes::isValid($method)) {
                $this->logger->error('An invalid request type was provided while processing a queue item.', [
                    'request' => $payload,
                ]);
                return;
            }

            try {
                $this->client->$method($payload);
            } catch (ProcessingException $e) {
                if ($method === RequestTypes::NEW_CLIENT
                    && $e->getCode() === ProcessingException::ERR_DUPLICATING_PHONE
                ) {
                    $this->client->updateClient($payload);
                }
                throw $e;
            }

            // Обновляем статус как выполненный
            $this->repository->updateStatus($item['id'], QueueStatuses::DONE);
        } catch (ProcessingException $e) {
            // Обновляем статус как ошибка
            $this->repository->updateStatus(
                $item['id'],
                QueueStatuses::ERROR,
            );
            // Логируем подробности ошибки
            $this->logger->error('Error processing queue item', [
                'payload' => $item['payload'] ?? null,
                'exception' => $e->__toString(),
                'message' => $e->getMessage()
            ]);
        }
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
            $this->repository->deleteQueue($item);
        }
    }
}
