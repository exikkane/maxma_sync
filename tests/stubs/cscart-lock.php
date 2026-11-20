<?php
/***************************************************************************
 *                             Larionov.tech                               *
 *                          https://larionov.tech                          *
 ***************************************************************************/

namespace Tygh\Lock;

interface StoreInterface {}

class Factory
{
    public function __construct(StoreInterface $store) {}
    public function createLock(string $resource, ?float $ttl = 30.0, bool $auto_release = true): Lock {}
}

class Lock
{
    public function acquire(bool $blocking = false): bool {}
    public function refresh(?float $ttl = null): void {}
    public function isExpired(): bool {}
    public function isAcquired(): bool {}
    public function release(): void {}
    public function wait(int $retry_sleep = 100, int $retry_count = PHP_INT_MAX): bool {}
}
