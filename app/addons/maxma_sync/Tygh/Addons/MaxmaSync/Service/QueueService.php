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
    private ?MaxmaClient $client;
    private QueueRepository $repository;
    private MaxmaLogger $logger;

    public function __construct(
        array $settings,
        ?MaxmaClient $client = null,
        ?QueueRepository $repository = null,
        ?MaxmaLogger $logger = null
    ) {
        $this->client = $client ?? new MaxmaClient($settings);
        $this->repository = $repository ?? new QueueRepository();
        $this->logger = $logger ?? new MaxmaLogger();
    }

    /**
     * Обработка всей очереди
     *
     * @return void
     */
    public function processQueue(): void
    {
        $items = $this->repository->getPending();
        if (!$items) return;

        $hasErrors = false;

        foreach ($items as $item) {
            try {
                $this->processItem($item);
            } catch (ProcessingException $e) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            throw new \Exception("Некоторые элементы очереди не были обработаны.");
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
