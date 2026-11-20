<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

use CloudLoyalty\Api\Exception\ProcessingException;
use CloudLoyalty\Api\Exception\TransportException;
use PHPUnit\Framework\TestCase;
use Tygh\Addons\MaxmaSync\Helpers\FactoryBuilders\ApplyReturnRequestBuilder;
use Tygh\Addons\MaxmaSync\Helpers\MaxmaLogger;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Repository\UserRepository;
use Tygh\Addons\MaxmaSync\Service\MaxmaClient;
use Tygh\Addons\MaxmaSync\Service\QueueService;
use Tygh\Addons\MaxmaSync\Service\UsersService;
use Tygh\Addons\MaxmaSync\Storage\UserCache;

final class QueueAndBalanceTest extends TestCase
{
    public function testQueueReportsTransportFailureAndContinuesAfterDuplicatePhone(): void
    {
        $repo = $this->createMock(QueueRepository::class);
        $repo->method('claim')->willReturn(true);
        $repo->method('acquireLock')->willReturn(true);
        $repo->method('getPending')->willReturn([
            ['id' => 1, 'entity_id' => 10, 'type' => 'newClient', 'payload' => '{"client":{}}'],
            ['id' => 2, 'entity_id' => 20, 'type' => 'setOrder', 'payload' => '{"orderId":"20"}'],
            ['id' => 3, 'entity_id' => 11, 'type' => 'newClient', 'payload' => '{"client":{}}'],
        ]);
        $statuses = [];
        $repo->method('updateStatus')->willReturnCallback(static function (int $id, string $status) use (&$statuses): void {
            $statuses[$id] = $status;
        });
        $client = $this->createMock(MaxmaClient::class);
        $client->method('newClient')->willThrowException(new ProcessingException('duplicate', ProcessingException::ERR_DUPLICATING_PHONE));
        $client->expects(self::exactly(2))->method('updateClient')->willReturn(new stdClass());
        $client->method('setOrder')->willThrowException(new TransportException('timeout'));
        $queue = new QueueService([], $client, $repo, $this->createMock(MaxmaLogger::class));

        try {
            $queue->processQueue();
            self::fail('A partially failed run must report failure');
        } catch (RuntimeException $e) {
            ksort($statuses);
            self::assertSame([1 => 'done', 2 => 'error', 3 => 'done'], $statuses);
        }
    }

    public function testOrderOperationsWaitForSetOrderAndClaim(): void
    {
        $repo = $this->createMock(QueueRepository::class);
        $repo->method('acquireLock')->willReturn(true);
        $repo->method('getPending')->willReturn([
            ['id' => 1, 'entity_id' => 20, 'type' => 'confirmOrder', 'payload' => '{"orderId":"20"}'],
            ['id' => 2, 'entity_id' => 20, 'type' => 'setOrder', 'payload' => '{"orderId":"20"}'],
            ['id' => 3, 'entity_id' => 21, 'type' => 'setOrder', 'payload' => '{"orderId":"21"}'],
        ]);
        $repo->method('claim')->willReturnCallback(static fn (int $id): bool => $id !== 3);
        $repo->method('latestStatus')->with('setOrder', 20)->willReturn('error');
        $end = [];
        $repo->method('updateStatus')->willReturnCallback(static function (int $id, string $status) use (&$end): void {
            $end[$id] = $status;
        });
        $client = $this->createMock(MaxmaClient::class);
        $client->expects(self::never())->method('confirmOrder');
        $client->expects(self::once())->method('setOrder')->willThrowException(new TransportException('timeout'));

        try {
            (new QueueService([], $client, $repo, $this->createMock(MaxmaLogger::class)))->processQueue();
            self::fail('An unresolved order must be reported');
        } catch (RuntimeException $e) {
            self::assertSame([2 => 'error', 1 => 'new'], $end);
        }
    }

    public function testNewerCancellationSupersedesConfirmationThatWasNotSent(): void
    {
        $repo = $this->createMock(QueueRepository::class);
        $repo->method('acquireLock')->willReturn(true);
        $repo->method('claim')->willReturn(true);
        $repo->method('getPending')->willReturn([
            ['id' => 1, 'type' => 'confirmOrder', 'payload' => '{"orderId":"20"}'],
            ['id' => 2, 'type' => 'cancelOrder', 'payload' => '{"orderId":"20"}'],
        ]);
        $repo->method('latestTerminalIntent')->with(20)->willReturn(['id' => 2, 'type' => 'cancelOrder', 'status' => 'new']);
        $repo->method('latestStatus')->with('setOrder', 20)->willReturn('done');
        $repo->expects(self::exactly(2))->method('updateStatus')->withConsecutive([1, 'superseded'], [2, 'done']);
        $client = $this->createMock(MaxmaClient::class);
        $client->expects(self::never())->method('confirmOrder');
        $client->expects(self::once())->method('cancelOrder')->willReturn(new stdClass());
        (new QueueService([], $client, $repo, $this->createMock(MaxmaLogger::class)))->processQueue();
    }

    public function testZeroBalanceIsSavedAndSessionBelongsToUser(): void
    {
        $session = [];
        $repo = $this->createMock(UserRepository::class);
        $repo->expects(self::once())->method('saveRewardPoints')->with(10, 0);
        $client = $this->createMock(MaxmaClient::class);
        $client->expects(self::once())->method('getBalance')->willReturn([]);
        $users = new UsersService(
            ['maxma_cache_ttl' => 120],
            $session,
            $client,
            $this->createMock(QueueRepository::class),
            $repo,
            $this->createMock(UserCache::class),
            null,
            $this->createMock(MaxmaLogger::class)
        );
        $users->saveUserBonusesData(10, ['balance' => 0], UsersService::BALANCE_CACHE_KEY);
        self::assertSame([], $users->getUserBonusesData(20, ['phone' => '+79990000000'], UsersService::BALANCE_CACHE_KEY));
    }

    public function testRefundAmountPreservesKopecks(): void
    {
        $request = (new ApplyReturnRequestBuilder())->build([
            'id' => 10, 'purchaseId' => 20, 'executedAt' => TIME, 'refundAmount' => 299.85,
            'items' => [['sku' => 'test', 'itemCount' => 3]],
        ]);
        self::assertSame(299.85, $request->getTransaction()->getRefundAmount());
    }
}
