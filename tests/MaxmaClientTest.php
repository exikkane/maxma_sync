<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

use CloudLoyalty\Api\Client;
use CloudLoyalty\Api\Exception\TransportException;
use PHPUnit\Framework\TestCase;
use Tygh\Addons\MaxmaSync\Service\MaxmaClient;

final class MaxmaClientTest extends TestCase
{
    public function testEmptyOrderResponseIsAnUnknownOutcome(): void
    {
        $sdk = $this->getMockBuilder(Client::class)->onlyMethods(['sendRawRequest'])->getMock();
        $sdk->method('sendRawRequest')->willReturn('{}');
        $client = new MaxmaClient(['api_key' => 'local-fixture'], $sdk);
        $this->expectException(TransportException::class);
        $client->setOrder(['orderId' => '20', 'calculationQuery' => [
            'client' => ['phoneNumber' => '+79990000000'],
            'shop' => ['code' => 'test', 'name' => 'Test'],
            'rows' => [], 'applyBonuses' => 0, 'collectBonuses' => 0,
        ]]);
    }

    public function testMalformedCalculationIsAnUnknownOutcome(): void
    {
        $sdk = $this->getMockBuilder(Client::class)->onlyMethods(['sendRawRequest'])->getMock();
        $sdk->method('sendRawRequest')->willReturn('{"calculationResult":{"rows":"bad"}}');
        $client = new MaxmaClient(['api_key' => 'local-fixture'], $sdk);
        $this->expectException(TransportException::class);
        $client->calculatePurchase(['calculationQuery' => [
            'shop' => ['code' => 'test', 'name' => 'Test'],
            'rows' => [], 'applyBonuses' => 0, 'collectBonuses' => 0,
        ]]);
    }
}
