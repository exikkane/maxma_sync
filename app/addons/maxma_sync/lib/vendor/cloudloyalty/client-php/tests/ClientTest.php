<?php

namespace Tests;

use CloudLoyalty\Api\Client;
use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Exception\TransportException;
use CloudLoyalty\Api\Generated\Model\AppliedOffer;
use CloudLoyalty\Api\Generated\Model\BonusHistoryEntry;
use CloudLoyalty\Api\Generated\Model\CalculateProductsRequest;
use CloudLoyalty\Api\Generated\Model\CalculateProductsRequestItem;
use CloudLoyalty\Api\Generated\Model\CalculateProductsResult;
use CloudLoyalty\Api\Generated\Model\CalculateProductsResultItem;
use CloudLoyalty\Api\Generated\Model\CalculateProductsResultItemBonuses;
use CloudLoyalty\Api\Generated\Model\CalculateProductsResultItemDiscounts;
use CloudLoyalty\Api\Generated\Model\ClientInfoQuery;
use CloudLoyalty\Api\Generated\Model\ClientInfoReply;
use CloudLoyalty\Api\Generated\Model\ClientQuery;
use CloudLoyalty\Api\Generated\Model\GetBonusHistoryRequest;
use CloudLoyalty\Api\Generated\Model\GetBonusHistoryResponse;
use CloudLoyalty\Api\Generated\Model\GetBonusHistoryResponsePagination;
use CloudLoyalty\Api\Generated\Model\GetGiftCardResponse;
use CloudLoyalty\Api\Generated\Model\GetHistoryRequest;
use CloudLoyalty\Api\Generated\Model\GetHistoryResponse;
use CloudLoyalty\Api\Generated\Model\GetHistoryResponsePagination;
use CloudLoyalty\Api\Generated\Model\GetPurchaseHistoryRequest;
use CloudLoyalty\Api\Generated\Model\GetPurchaseHistoryResponse;
use CloudLoyalty\Api\Generated\Model\GetPurchaseHistoryResponsePagination;
use CloudLoyalty\Api\Generated\Model\GiftCard;
use CloudLoyalty\Api\Generated\Model\GiftCardQuery;
use CloudLoyalty\Api\Generated\Model\HistoryEntry;
use CloudLoyalty\Api\Generated\Model\HistoryEntryPurchase;
use CloudLoyalty\Api\Generated\Model\NewClientRequest;
use CloudLoyalty\Api\Generated\Model\NewClientResponse;
use CloudLoyalty\Api\Generated\Model\Product;
use CloudLoyalty\Api\Generated\Model\PurchaseBonuses;
use CloudLoyalty\Api\Generated\Model\PurchaseDiscounts;
use CloudLoyalty\Api\Generated\Model\PurchaseHistoryPurchase;
use CloudLoyalty\Api\Generated\Model\PurchaseRow;
use CloudLoyalty\Api\Generated\Model\ShopQuery;
use CloudLoyalty\Api\Http\Request;
use CloudLoyalty\Api\Http\Response;
use CloudLoyalty\Api\Logger\PsrBridgeLogger;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testNewClient()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo(
                (new Request())
                    ->setMethod('POST')
                    ->setUri('https://api.maxma.com/new-client')
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Processing-Key' => 'test-key',
                        'Accept-Language' => 'ru'
                    ])
                    ->setBody('{"client":{"name":"Name"}}')
            ))
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"client":{"phoneNumber":"+79995566777"}}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $response = $apiClient->newClient(
            (new NewClientRequest())
                ->setClient(
                    (new ClientInfoQuery())
                        ->setName('Name')
                )
        );

        $this->assertEquals(
            (new NewClientResponse())
                ->setClient(
                    (new ClientInfoReply())
                        ->setPhoneNumber('+79995566777')
                ),
            $response
        );
    }

    public function testNewClientWhenProcessingError()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"errorCode":20,"description":"Incorrect phone","hint":"Fix the phone"}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $this->expectExceptionObject(new ProcessingException(
            'Incorrect phone',
            ProcessingException::ERR_INCORRECT_PHONE,
            'Fix the phone'
        ));

        $apiClient->newClient(
            (new NewClientRequest())
                ->setClient(
                    (new ClientInfoQuery())
                        ->setName('Name')
                )
        );
    }

    public function testNewClientWhenTransportException()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new TransportException('Internal Server Error', 500));

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $this->expectExceptionObject(new TransportException(
            'Internal Server Error',
            500
        ));

        $apiClient->newClient(
            (new NewClientRequest())
                ->setClient(
                    (new ClientInfoQuery())
                        ->setName('Name')
                )
        );
    }

    public function testNewClientWhenCustomServerAddressSpecified()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                $this->assertEquals('https://api.someotherhost.com/new-client', $request->getUri());

                return true;
            }))
            ->willReturn(new Response());

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setServerAddress('api.someotherhost.com');
        $apiClient->setProcessingKey('test-key');

        $apiClient->newClient(new NewClientRequest());
    }

    public function testNewClientWhenCustomServerAddressWithSchemeSpecified()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                $this->assertEquals('ftp://api.someotherhost.com/new-client', $request->getUri());

                return true;
            }))
            ->willReturn(new Response());

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setServerAddress('ftp://api.someotherhost.com');
        $apiClient->setProcessingKey('test-key');

        $apiClient->newClient(new NewClientRequest());
    }

    public function testNewClientWhenPsrLoggerProvided()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');
        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"success":true}')
            );

        $loggerMock = $this->createMock('Psr\Log\LoggerInterface');
        $loggerMock->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Request'),
                $this->callback(function (array $context) {
                    $this->assertEquals(
                        '{"method":"POST","uri":"https:\/\/api.maxma.com\/new-client",' .
                        '"headers":{"X-Processing-Key":"test-key","Content-Type":"application\/json",' .
                        '"Accept":"application\/json","Accept-Language":"ru"},' .
                        '"body":"{\"client\":{\"name\":\"Name\"}}"}',
                        json_encode($context['request'])
                    );
                    $this->assertEquals(
                        '{"statusCode":200,"reasonPhrase":"OK","headers":{"Content-Type":"application\/json"},' .
                        '"body":"{\"success\":true}"}',
                        json_encode($context['response'])
                    );

                    return true;
                })
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');
        $apiClient->setLogger(new PsrBridgeLogger($loggerMock));

        $apiClient->newClient(
            (new NewClientRequest())
                ->setClient(
                    (new ClientInfoQuery())
                        ->setName('Name')
                )
        );
    }

    public function testCalculateProducts()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo(
                (new Request())
                    ->setMethod('POST')
                    ->setUri('https://api.maxma.com/calculate-products')
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Processing-Key' => 'test-key',
                        'Accept-Language' => 'ru'
                    ])
                    ->setBody('{"client":{"phoneNumber":"+79990000000"},"shop":{"code":"shop1","name":"Shop 1"},' .
                        '"products":[{"product":{"externalId":"external-id","blackPrice":400,"minPrice":0,"vatPercent":0},' .
                        '"externalDiscount":0,"noCollectBonuses":false,"noOffer":false}],"discountRoundStep":0}')
            ))
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"products":[{"bonuses":{"collected":200},"discounts":{"offer":300},' .
                        '"offers":[{"name":"Offer1","amount":300,"expireAt":"2023-12-20T16:00:00+03:00"}]}]}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $response = $apiClient->calculateProducts(
            (new CalculateProductsRequest())
                ->setClient(
                    (new ClientQuery())
                        ->setPhoneNumber('+79990000000')
                )
                ->setShop(
                    (new ShopQuery())
                        ->setCode('shop1')
                        ->setName('Shop 1')
                )
                ->setProducts([
                    (new CalculateProductsRequestItem())
                        ->setProduct(
                            (new Product())
                                ->setExternalId('external-id')
                                ->setBlackPrice(400)
                        )
                ])
        );

        $this->assertEquals(
            (new CalculateProductsResult())
                ->setProducts([
                    (new CalculateProductsResultItem())
                        ->setBonuses(
                            (new CalculateProductsResultItemBonuses())
                                ->setCollected(200)
                        )
                        ->setDiscounts(
                            (new CalculateProductsResultItemDiscounts())
                                ->setOffer(300)
                        )
                        ->setOffers([
                            (new AppliedOffer())
                                ->setName('Offer1')
                                ->setAmount(300)
                                ->setExpireAt(new \DateTime('2023-12-20T16:00:00+03:00'))
                        ])
                ]),
            $response
        );
    }

    public function testGetHistory()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo(
                (new Request())
                    ->setMethod('POST')
                    ->setUri('https://api.maxma.com/get-history')
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Processing-Key' => 'test-key',
                        'Accept-Language' => 'ru'
                    ])
                    ->setBody('{"client":{"phoneNumber":"+79990000000"}}')
            ))
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"history":[{"at":"2018-05-02T16:12:54+03:00","amount":63,' .
                        '"operation":"OPERATION_APPLIED","operationName":"Оплата покупки",' .
                        '"OPERATION_APPLIED":{"purchaseId":"66379","executedAt":"2018-05-02T16:12:50+03:00",' .
                        '"totalAmount":2300}},{"at":"2018-04-26T00:00:13+03:00","amount":-47,' .
                        '"operation":"OPERATION_EXPIRED","operationName":"Списание по истечении срока",' .
                        '"OPERATION_EXPIRED":{}}],"pagination":{"total":2}}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $response = $apiClient->getHistory(
            (new GetHistoryRequest())
                ->setClient(
                    (new ClientQuery())
                        ->setPhoneNumber('+79990000000')
                )
        );

        $this->assertEquals(
            (new GetHistoryResponse())
                ->setHistory([
                    [
                        'at' => '2018-05-02T16:12:54+03:00',
                        'amount' => 63,
                        'operation' => 'OPERATION_APPLIED',
                        'operationName' => 'Оплата покупки',
                        'OPERATION_APPLIED' => [
                            'purchaseId' => '66379',
                            'executedAt' => '2018-05-02T16:12:50+03:00',
                            'totalAmount' => 2300
                        ]
                    ],
                    [
                        'at' => '2018-04-26T00:00:13+03:00',
                        'amount' => -47,
                        'operation' => 'OPERATION_EXPIRED',
                        'operationName' => 'Списание по истечении срока',
                        'OPERATION_EXPIRED' => []
                    ]
                ])
                ->setPagination(
                    (new GetHistoryResponsePagination())
                        ->setTotal(2)
                ),
            $response
        );
    }

    public function testGetBonusHistory()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo(
                (new Request())
                    ->setMethod('POST')
                    ->setUri('https://api.maxma.com/get-bonus-history')
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Processing-Key' => 'test-key',
                        'Accept-Language' => 'ru'
                    ])
                    ->setBody('{"client":{"phoneNumber":"+79990000000"}}')
            ))
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"history":[{"at":"2018-05-02T16:12:54+03:00","amount":63,' .
                        '"operation":"OPERATION_APPLIED","operationName":"Оплата покупки",' .
                        '"OPERATION_APPLIED":{"purchaseId":"66379","executedAt":"2018-05-02T16:12:50+03:00",' .
                        '"totalAmount":2300}},{"at":"2018-04-26T00:00:13+03:00","amount":-47,' .
                        '"operation":"OPERATION_EXPIRED","operationName":"Списание по истечении срока",' .
                        '"OPERATION_EXPIRED":{}}],"pagination":{"total":2}}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $response = $apiClient->getBonusHistory(
            (new GetBonusHistoryRequest())
                ->setClient(
                    (new ClientQuery())
                        ->setPhoneNumber('+79990000000')
                )
        );

        $this->assertEquals(
            (new GetBonusHistoryResponse())
                ->setHistory([
                    (new BonusHistoryEntry())
                        ->setAt(new \DateTime('2018-05-02T16:12:54+03:00'))
                        ->setAmount(63)
                        ->setOperation('OPERATION_APPLIED')
                        ->setOperationName('Оплата покупки')
                        ->setOPERATION_APPLIED(
                            (new HistoryEntryPurchase())
                                ->setPurchaseId(66379)
                                ->setExecutedAt('2018-05-02T16:12:50+03:00')
                                ->setTotalAmount(2300)
                        ),
                    (new BonusHistoryEntry())
                        ->setAt(new \DateTime('2018-04-26T00:00:13+03:00'))
                        ->setAmount(-47)
                        ->setOperation('OPERATION_EXPIRED')
                        ->setOperationName('Списание по истечении срока')
                        ->setOPERATION_EXPIRED([])
                ])
                ->setPagination(
                    (new GetBonusHistoryResponsePagination())
                        ->setTotal(2)
                ),
            $response
        );
    }

    public function testGetPurchaseHistory()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo(
                (new Request())
                    ->setMethod('POST')
                    ->setUri('https://api.maxma.com/get-purchase-history')
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Processing-Key' => 'test-key',
                        'Accept-Language' => 'ru'
                    ])
                    ->setBody('{"client":{"phoneNumber":"+79990000000"}}')
            ))
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"history":[{"shop":{"code":"shop1","name":"Shop1"},"orderId":"order1",' .
                        '"orderStatus":"STATUS_NEW","txid":"order1","executedAt":"2025-01-11T00:00:00+03:00",' .
                        '"totalAmount":2016,"totalDiscount":16,"paidAmount":2000,"discounts":{"auto":4,' .
                        '"manual":5,"bonuses":10,"promocode":2,"offer":3,"rounding":1},"bonuses":{"applied":' .
                        '10,"collected":5},"rows":[{"externalId":"id_demiseason_jamper_1","title":' .
                        '"Демисезонная куртка1","sku":"sku-01","qty":1,"price":100,"totalAmount":100,' .
                        '"totalDiscount":0,"paidAmount":100,"discounts":{"auto":0,"manual":0,"bonuses":0,' .
                        '"promocode":0,"offer":0,"rounding":0},"bonuses":{"applied":0,"collected":0}}],' .
                        '"prepaidAmount":0}],"pagination":{"total":2}}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $response = $apiClient->getPurchaseHistory(
            (new GetPurchaseHistoryRequest())
                ->setClient(
                    (new ClientQuery())
                        ->setPhoneNumber('+79990000000')
                )
        );

        $this->assertTrue($response instanceof GetPurchaseHistoryResponse);
        $this->assertTrue($response->getPagination() instanceof  GetPurchaseHistoryResponsePagination);
        $this->assertTrue($response->getHistory()[0] instanceof PurchaseHistoryPurchase);
        $this->assertTrue($response->getHistory()[0]->getShop() instanceof ShopQuery);
        $this->assertTrue($response->getHistory()[0]->getExecutedAt() instanceof \DateTime);
        $this->assertTrue($response->getHistory()[0]->getDiscounts() instanceof PurchaseDiscounts);
        $this->assertTrue($response->getHistory()[0]->getBonuses() instanceof PurchaseBonuses);
        $this->assertTrue($response->getHistory()[0]->getRows()[0] instanceof PurchaseRow);
        $this->assertTrue($response->getHistory()[0]->getRows()[0]->getBonuses() instanceof PurchaseBonuses);
        $this->assertTrue($response->getHistory()[0]->getRows()[0]->getDiscounts() instanceof PurchaseDiscounts);
    }

    public function testGetGiftCard()
    {
        $httpClientMock = $this->createMock('CloudLoyalty\Api\Http\Client\NativeClient');

        $httpClientMock->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo(
                (new Request())
                    ->setMethod('POST')
                    ->setUri('https://api.maxma.com/get-gift-card')
                    ->setHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-Processing-Key' => 'test-key',
                        'Accept-Language' => 'ru'
                    ])
                    ->setBody('{"code":"2020728300990364"}')
            ))
            ->willReturn(
                (new Response())
                    ->setStatusCode(200)
                    ->setReasonPhrase('OK')
                    ->setHeaders(['Content-Type' => 'application/json'])
                    ->setBody('{"giftCard":{"number":"405","code":"2020728300990364","sku":"MXGCLM7XFM",' .
                        '"initAmount":500,"balance":500,"status":"ACTIVE","activatedAt":' .
                        '"2025-11-20T09:38:15.190Z","blockedAt":"2025-11-20T09:38:15.190Z",' .
                        '"validFrom":"2025-11-20T09:38:15.190Z","validUntil":"2025-11-20T09:38:15.190Z"}}')
            );

        $apiClient = new Client([], $httpClientMock);
        $apiClient->setProcessingKey('test-key');

        $response = $apiClient->getGiftCard(
            (new GiftCardQuery())
                ->setCode('2020728300990364')
        );

        $this->assertEquals(
            (new GetGiftCardResponse())
                ->setGiftCard(
                    (new GiftCard())
                        ->setCode('2020728300990364')
                        ->setNumber(405)
                        ->setSku('MXGCLM7XFM')
                        ->setInitAmount(500)
                        ->setBalance(500)
                        ->setStatus('ACTIVE')
                        ->setActivatedAt(new \DateTime('2025-11-20T09:38:15.190Z'))
                        ->setBlockedAt(new \DateTime('2025-11-20T09:38:15.190Z'))
                        ->setValidFrom(new \DateTime('2025-11-20T09:38:15.190Z'))
                        ->setValidUntil(new \DateTime('2025-11-20T09:38:15.190Z'))
                ),
            $response
        );
    }
}
