<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Client;
use CloudLoyalty\Api\Exception\ProcessingException;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\CalculatePurchaseRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\CancelOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\ConfirmOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\GetBalanceRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\GetHistoryRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\NewClientRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\SetOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\UpdateClientRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;
use Tygh\Addons\MaxmaSync\Helpers\RequestFactory;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class MaxmaClient
{
    private Client $client;
    private MaxmaLogger $logger;
    private RequestFactory $requestFactory;

    public function __construct(array $settings)
    {
        $api_key = $settings['api_key'];
        $test_mode = $settings['maxma_test_mode'];

        $server_address = $test_mode == 'Y'
            ? 'https://api-test.maxma.com'
            : 'https://api.maxma.com';

        $this->client = new Client();

        $this->client->setProcessingKey($api_key);
        $this->client->setServerAddress($server_address);
        $this->logger = new MaxmaLogger();

        $this->requestFactory = new RequestFactory([
            new NewClientRequestBuilder(),
            new UpdateClientRequestBuilder(),
            new ConfirmOrderRequestBuilder(),
            new CancelOrderRequestBuilder(),
            new CalculatePurchaseRequestBuilder(),
            new SetOrderRequestBuilder(),
            new GetBalanceRequestBuilder(),
            new GetHistoryRequestBuilder(),

        ]);
    }
    /**
     * Универсальный вызов метода SDK
     */
    public function call(string $method, array $payload)
    {
        try {
            $request = $this->requestFactory->make($method, $payload);
            return $this->client->$method($request);
        } catch (ProcessingException $e) {

            fn_print_r($e->getMessage(), $e->getHint()); // TODO реализовать нормальное логирование
            throw $e;
        }
    }

    public function newClient($request)       { return $this->call(RequestTypes::NEW_CLIENT, $request); }
    public function calculatePurchase($request) { return $this->call(RequestTypes::CALCULATE_PURCHASE, $request); }
    public function setOrder($request)       { return $this->call(RequestTypes::SET_ORDER, $request); }
    public function confirmOrder($request)   { return $this->call(RequestTypes::CONFIRM_ORDER, $request); }
    public function cancelOrder($request)    { return $this->call(RequestTypes::CANCEL_ORDER, $request); }
    public function applyReturn($request)    { return $this->call(RequestTypes::APPLY_RETURN, $request); }
    public function updateClient($request)     { return $this->call(RequestTypes::UPDATE_CLIENT, $request); }
    public function getBalance($request): array {
        $response = $this->call(RequestTypes::GET_BALANCE, $request);

        if (!$response) {
            return [];
        }

        $client_info = $response->getClient();

        return [
            'balance' => $client_info->getBonuses(),
            'pending_bonuses' => $client_info->getPendingBonuses(),
        ];
    }
    public function getBonusHistory($request): array {
        $response = $this->call(RequestTypes::GET_BONUS_HISTORY, $request);

        if (!$response) {
            return [];
        }

        $history_info = $response->getHistory() ?? [];

        $history = [];
        foreach ($history_info as $entry) {
            $history[] = [
                'date' => $entry->getAt()->format('Y-m-d H:i:s'),
                'amount' => $entry->getAmount(),
                'operation' => $entry->getOperation(),
                'operation_name' => $entry->getOperationName(),
            ];
        }
        $history['pagination'] = $response->getPagination()->getTotal();

        return $history;
    }
}
