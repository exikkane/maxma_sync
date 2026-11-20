<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace Tygh\Addons\MaxmaSync\Service;

use RuntimeException;
use Tygh\Lock\Factory;
use Tygh\Lock\Lock;

final class QueueLocks
{
    private const LIFETIME_SECONDS = 60.0;
    public const ENQUEUE_WAIT_SECONDS = 5;

    public function __construct(private readonly Factory $factory)
    {
    }

    /**
     * Acquire the CS-Cart lock for a queue operation within the given wait budget.
     */
    public function acquire(string $scope, int $wait_seconds = 0): ?Lock
    {
        $lock = $this->factory->createLock('maxma_sync:' . $scope, self::LIFETIME_SECONDS);
        $deadline = hrtime(true) + $wait_seconds * 1_000_000_000;
        do {
            if ($lock->acquire()) {
                return $lock;
            }

            $remaining_ms = (int) floor(($deadline - hrtime(true)) / 1_000_000);
            if ($remaining_ms <= 0) {
                return null;
            }
            $lock->wait(min(100, $remaining_ms), 1);
        } while (hrtime(true) < $deadline);

        return null;
    }

    /**
     * Renew an acquired lock before the worker claims more jobs.
     */
    public function refresh(Lock $lock): void
    {
        if ($lock->isExpired()) {
            throw new RuntimeException(__('maxma_sync.queue_busy'));
        }
        $lock->refresh();
    }
}
