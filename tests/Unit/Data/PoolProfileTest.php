<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Data\PoolProfile;

class PoolProfileTest extends TestCase
{
    public function test_creates_pool_profile_with_all_properties(): void
    {
        $profile = new PoolProfile(
            size: 10,
            active: 5,
            idle: 5,
            waiting: 2,
            timestamp: 1234567890.123,
            connectionName: 'pgsql',
        );

        $this->assertEquals(10, $profile->size);
        $this->assertEquals(5, $profile->active);
        $this->assertEquals(5, $profile->idle);
        $this->assertEquals(2, $profile->waiting);
        $this->assertEquals(1234567890.123, $profile->timestamp);
        $this->assertEquals('pgsql', $profile->connectionName);
    }

    public function test_is_exhausted_detects_pool_exhaustion(): void
    {
        $exhaustedPool = new PoolProfile(
            size: 10,
            active: 10,
            idle: 0,
            waiting: 3,
            timestamp: microtime(true),
        );

        $notExhaustedPool = new PoolProfile(
            size: 10,
            active: 5,
            idle: 5,
            waiting: 0,
            timestamp: microtime(true),
        );

        $this->assertTrue($exhaustedPool->isExhausted());
        $this->assertFalse($notExhaustedPool->isExhausted());
    }

    public function test_is_underutilized_detects_low_usage(): void
    {
        $underutilizedPool = new PoolProfile(
            size: 10,
            active: 2,
            idle: 8,
            waiting: 0,
            timestamp: microtime(true),
        );

        $normalPool = new PoolProfile(
            size: 10,
            active: 7,
            idle: 3,
            waiting: 0,
            timestamp: microtime(true),
        );

        $this->assertTrue($underutilizedPool->isUnderutilized(0.3));
        $this->assertFalse($normalPool->isUnderutilized(0.3));
    }

    public function test_get_utilization_calculates_percentage(): void
    {
        $pool = new PoolProfile(
            size: 10,
            active: 7,
            idle: 3,
            waiting: 0,
            timestamp: microtime(true),
        );

        $this->assertEquals(70.0, $pool->getUtilization());
    }

    public function test_get_utilization_handles_zero_size(): void
    {
        $pool = new PoolProfile(
            size: 0,
            active: 0,
            idle: 0,
            waiting: 0,
            timestamp: microtime(true),
        );

        $this->assertEquals(0.0, $pool->getUtilization());
    }

    public function test_has_waiting_detects_waiting_coroutines(): void
    {
        $poolWithWaiting = new PoolProfile(
            size: 10,
            active: 10,
            idle: 0,
            waiting: 5,
            timestamp: microtime(true),
        );

        $poolWithoutWaiting = new PoolProfile(
            size: 10,
            active: 5,
            idle: 5,
            waiting: 0,
            timestamp: microtime(true),
        );

        $this->assertTrue($poolWithWaiting->hasWaiting());
        $this->assertFalse($poolWithoutWaiting->hasWaiting());
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $profile = new PoolProfile(
            size: 10,
            active: 7,
            idle: 3,
            waiting: 2,
            timestamp: 1234567890.123,
            connectionName: 'pgsql',
        );

        $array = $profile->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(10, $array['size']);
        $this->assertEquals(7, $array['active']);
        $this->assertEquals(3, $array['idle']);
        $this->assertEquals(2, $array['waiting']);
        $this->assertEquals(1234567890.123, $array['timestamp']);
        $this->assertEquals('pgsql', $array['connection_name']);
        $this->assertEquals(70.0, $array['utilization']);
        $this->assertFalse($array['is_exhausted']);
        $this->assertTrue($array['has_waiting']);
    }
}
