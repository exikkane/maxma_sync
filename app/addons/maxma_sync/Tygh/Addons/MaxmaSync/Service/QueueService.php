<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Exception\ProcessingException;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;
use Tygh\Enum\Addons\MaxmaSync\QueueStatuses;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;

class QueueService
{
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
     */
    public function processQueue(): void
    {
        $items = $this->repository->getPending();
        if (!$items) {
            return;
        }
        foreach ($items as $item) {
            $this->processItem($item, $this->client, $this->logger);
        }
    }

    /**
     * Обработка одного элемента очереди
     */
    private function processItem(array $item, MaxmaClient $client, MaxmaLogger $logger): void
    {
        try {
            $this->repository->updateStatus($item['id'], QueueStatuses::PROCESSING);

            $method  = $item['type'];
            $payload = json_decode($item['payload'], true, 512, JSON_THROW_ON_ERROR);

            if (!RequestTypes::isValid($method)) {
                $logger->error('An invalid request type was provided while processing a queue item.', [
                    'request' => $payload,
                ]);

                return;
            }

            try {
                $client->$method($payload);
            } catch (ProcessingException $e) {
                if (
                    $method === RequestTypes::NEW_CLIENT
                    && $e->getCode() === ProcessingException::ERR_DUPLICATING_PHONE
                ) {
                    $client->updateClient($payload);
                }
            }

            $this->repository->updateStatus($item['id'], QueueStatuses::ERROR);
        } catch (ProcessingException $e) {
            $this->repository->updateStatus(
                $item['id'],
                QueueStatuses::ERROR,
                $e->getMessage()
            );

            $logger->error('An error was appeared while processing a queue item.', [
                'item' => $item,
                'exception' => $e->getHint()
            ]);
        }
    }
}
