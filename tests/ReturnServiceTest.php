<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

use PHPUnit\Framework\TestCase;
use Tygh\Addons\MaxmaSync\Repository\QueueRepository;
use Tygh\Addons\MaxmaSync\Repository\ReturnRepository;
use Tygh\Addons\MaxmaSync\Service\ReturnService;

final class ReturnServiceTest extends TestCase
{
    public function testSeparateReturnsRespectQuantityAndAreNotRequeued(): void
    {
        $item = ['return_id' => 10, 'timestamp' => TIME, 'amount' => 3, 'sku' => 'A', 'product_id' => 1, 'price' => '99.95'];
        $other = ['return_id' => 11, 'amount' => 1, 'sku' => null, 'product_id' => 2, 'price' => '12.75'] + $item;
        $repo = $this->createMock(ReturnRepository::class);
        $repo->method('getApprovedItems')->with(20)->willReturn([$item, $other]);
        $queue = $this->createMock(QueueRepository::class);
        $queue->method('acquireLock')->willReturn(true);
        $payloads = [];
        $queue->method('hasReturn')->willReturnCallback(static function (int $id) use (&$payloads): bool {
            return isset($payloads[$id]);
        });
        $queue->expects(self::exactly(2))->method('add')->willReturnCallback(
            static function (string $type, int $id, array $payload) use (&$payloads): int {
                self::assertSame('applyReturn', $type);
                $payloads[$id] = $payload;
                return $id;
            }
        );

        $service = new ReturnService($repo, $queue);
        $service->enqueueApprovedReturns(20, []);
        $service->enqueueApprovedReturns(20, []);
        self::assertSame(299.85, $payloads[10]['refundAmount']);
        self::assertSame(12.75, $payloads[11]['refundAmount']);
        self::assertSame(20, $payloads[10]['purchaseId']);
        self::assertSame([['sku' => '2', 'itemCount' => 1]], $payloads[11]['items']);
    }
}
