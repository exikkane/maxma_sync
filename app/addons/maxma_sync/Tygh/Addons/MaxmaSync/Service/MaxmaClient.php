<?php

namespace Tygh\Addons\MaxmaSync\Service;

use CloudLoyalty\Api\Client;
use CloudLoyalty\Api\Generated\Model;
use CloudLoyalty\Api\Exception\TransportException;
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
    private bool $hasProcessingKey;

    /**
     * @param array<array-key, mixed> $settings
     */
    public function __construct(
        array $settings,
        private readonly Client $client = new Client(),
    )
    {
        $api_key = (string) ($settings['api_key'] ?? '');
        $this->hasProcessingKey = trim($api_key) !== '';
        $test_mode = $settings['maxma_test_mode'] ?? 'N';

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
     * @param array<array-key, mixed> $payload
     */
    private function call(string $method, array $payload): object
    {
        if (!$this->hasProcessingKey) {
            throw new TransportException('MAXMA processing key is not configured.');
        }
        $request = $this->requestFactory->make($method, $payload);
        try {
            $response = $this->client->$method($request);
        } catch (\TypeError | \JsonException $e) {
            throw new TransportException('MAXMA response could not be decoded.', 0, $e);
        }
        if (!is_object($response)) {
            throw new TransportException('MAXMA returned an invalid response.');
        }
        return $response;
    }

    /**
     * @param array<array-key, mixed> $request
     */
    public function newClient(array $request): object
    {
        return $this->call(RequestTypes::NEW_CLIENT, $request);
    }
    /**
     * @param array<array-key, mixed> $request
     */
    public function calculatePurchase(array $request): Model\V2CalculatePurchaseResponse
    {
        $response = $this->call(RequestTypes::CALCULATE_PURCHASE, $request);
        if (!$response instanceof Model\V2CalculatePurchaseResponse || $response->getCalculationResult()?->getSummary() === null) {
            throw new TransportException('Invalid MAXMA calculation response.');
        }
        return $response;
    }
    /**
     * @param array<array-key, mixed> $request
     */
    public function setOrder(array $request): object
    {
        $response = $this->call(RequestTypes::SET_ORDER, $request);
        if (!$response instanceof Model\V2SetOrderResponse || $response->getCalculationResult()?->getSummary() === null) {
            throw new TransportException('MAXMA returned no order calculation.');
        }
        return $response;
    }
    /**
     * @param array<array-key, mixed> $request
     */
    public function confirmOrder(array $request): object
    {
        $response = $this->call(RequestTypes::CONFIRM_ORDER, $request);
        if (!$response instanceof Model\ConfirmOrderResponse || $response->getOperationResult() === null) {
            throw new TransportException('MAXMA returned no operation result.');
        }
        return $response;
    }
    /**
     * @param array<array-key, mixed> $request
     */
    public function cancelOrder(array $request): object
    {
        $response = $this->call(RequestTypes::CANCEL_ORDER, $request);
        if (!$response instanceof Model\CancelOrderResponse || $response->getOperationResult() === null) {
            throw new TransportException('MAXMA returned no operation result.');
        }
        return $response;
    }
    /**
     * @param array<array-key, mixed> $request
     */
    public function applyReturn(array $request): object
    {
        $response = $this->call(RequestTypes::APPLY_RETURN, $request);
        if (!$response instanceof Model\ApplyReturnResponse || $response->getConfirmation() === null) {
            throw new TransportException('MAXMA returned no operation result.');
        }
        return $response;
    }
    /**
     * @param array<array-key, mixed> $request
     */
    public function updateClient(array $request): object
    {
        return $this->call(RequestTypes::UPDATE_CLIENT, $request);
    }
    /**
     * @param array<array-key, mixed> $request
     * @return array<array-key, mixed>
     */
    public function getBalance(array $request): array {
        $response = $this->call(RequestTypes::GET_BALANCE, $request);

        if (!$response instanceof Model\GetBalanceResponse) {
            throw new TransportException('Invalid MAXMA balance response.');
        }

        return BalanceDto::fromApiResponse($response)?->toArray() ?? [];
    }
    /**
     * @param array<array-key, mixed> $request
     * @return array<array-key, mixed>
     */
    public function getBonusHistory(array $request): array {
        $response = $this->call(RequestTypes::GET_BONUS_HISTORY, $request);

        if (!$response instanceof Model\GetBonusHistoryResponse) {
            throw new TransportException('Invalid MAXMA history response.');
        }

        return BonusHistoryDto::fromApiResponse($response)->toArray();
    }
}
