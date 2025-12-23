<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Client;
use CloudLoyalty\Api\Exception\TransportException;
use CloudLoyalty\Api\Exception\ProcessingException;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;
use Tygh\Addons\MaxmaSync\Helpers\RequestFactory;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class MaxmaClient
{
    private static ?self $instance = null;
    private Client $client;
    private MaxmaLogger $logger;

    private function __construct(array $settings)
    {
        $this->client = new Client();

        $api_key = $settings['api_key'];
        $this->client->setProcessingKey($api_key);
        $this->logger = new MaxmaLogger();
    }

    /**
     * Получить единственный экземпляр
     */
    public static function getInstance(array $settings): self
    {
        if (self::$instance === null) {
            self::$instance = new self($settings);
        }
        return self::$instance;
    }

    /**
     * Универсальный вызов метода SDK
     */
    public function call(string $method, array $payload)
    {
        try {
            $request = RequestFactory::make($method, $payload);
            return $this->client->$method($request);
        } catch (TransportException | ProcessingException $e) {
            $this->logger->error("Error calling {$method}: {$e->getMessage()}", [
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    public function newClient($request)       { return $this->call(RequestTypes::NEW_CLIENT, $request); }
    public function calculatePurchase($request) { return $this->call(RequestTypes::CALCULATE_PURCHASE, $request); }
    public function setOrder($request)       { return $this->call(RequestTypes::SET_ORDER, $request); }
    public function confirmOrder($request)   { return $this->call(RequestTypes::SET_ORDER, $request); }
    public function cancelOrder($request)    { return $this->call(RequestTypes::CANCEL_ORDER, $request); }
    public function applyReturn($request)    { return $this->call(RequestTypes::APPLY_RETURN, $request); }
    public function updateClient($request)     { return $this->call(RequestTypes::UPDATE_CLIENT, $request); }
    public function getBalance($request): array {
        $response = $this->call(RequestTypes::GET_BALANCE, $request);

        return [
            'balance' => $response->getBonuses(),
            'pending_bonuses' => $response->getPendingBonuses(),
        ];
    }
    public function getBonusHistory($request): array {
        $response = $this->call(RequestTypes::GET_BONUS_HISTORY, $request);
        return [
            'history' => $response->getBonusHistory(),
            'pagination' => $response->getPagination()->getTotal(),
        ];
    }
}
