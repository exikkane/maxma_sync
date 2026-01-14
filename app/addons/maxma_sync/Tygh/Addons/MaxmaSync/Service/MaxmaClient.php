<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Client;
use Tygh\Addons\MaxmaSync\Dto\BalanceDto;
use Tygh\Addons\MaxmaSync\Dto\BonusHistoryDto;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\ApplyReturnRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\CalculatePurchaseRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\CancelOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\ConfirmOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\GetBalanceRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\GetHistoryRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\NewClientRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\SetOrderRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\UpdateClientRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\RequestFactory;
use Tygh\Enum\Addons\MaxmaSync\RequestTypes;

class MaxmaClient
{
    private RequestFactory $requestFactory;
    private Client $client;

    public function __construct(array $settings, ?Client $client = null)
    {
        $api_key = $settings['api_key'];
        $test_mode = $settings['maxma_test_mode'];
        $this->client = $client ?? new Client();
        $server_address = $test_mode === 'Y'
            ? Client::TEST_SERVER_ADDRESS
            : Client::DEFAULT_SERVER_ADDRESS;

        $this->client->setProcessingKey($api_key);
        $this->client->setServerAddress($server_address);

        $this->requestFactory = new RequestFactory([
            new NewClientRequestBuilder(),
            new UpdateClientRequestBuilder(),
            new ConfirmOrderRequestBuilder(),
            new CancelOrderRequestBuilder(),
            new CalculatePurchaseRequestBuilder(),
            new SetOrderRequestBuilder(),
            new GetBalanceRequestBuilder(),
            new GetHistoryRequestBuilder(),
            new ApplyReturnRequestBuilder(),
        ]);
    }
    /**
     * Универсальный вызов метода SDK
     */
    public function call(string $method, array $payload)
    {
        $request = $this->requestFactory->make($method, $payload);
        return $this->client->$method($request);
    }

    public function newClient($request)
    {
        return $this->call(RequestTypes::NEW_CLIENT, $request);
    }
    public function calculatePurchase($request)
    {
        return $this->call(RequestTypes::CALCULATE_PURCHASE, $request);
    }
    public function setOrder($request)
    {
        return $this->call(RequestTypes::SET_ORDER, $request);
    }
    public function confirmOrder($request)
    {
        return $this->call(RequestTypes::CONFIRM_ORDER, $request);
    }
    public function cancelOrder($request)
    {
        return $this->call(RequestTypes::CANCEL_ORDER, $request);
    }
    public function applyReturn($request)
    {
        return $this->call(RequestTypes::APPLY_RETURN, $request);
    }
    public function updateClient($request)
    {
        return $this->call(RequestTypes::UPDATE_CLIENT, $request);
    }
    public function getBalance($request): array {
        $response = $this->call(RequestTypes::GET_BALANCE, $request);

        if (!$response) {
            return [];
        }

        return BalanceDto::fromApiResponse($response)->toArray();
    }
    public function getBonusHistory($request): array {
        $response = $this->call(RequestTypes::GET_BONUS_HISTORY, $request);

        if (!$response) {
            return [];
        }

        return BonusHistoryDto::fromApiResponse($response)->toArray();
    }
}
