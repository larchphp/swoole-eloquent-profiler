<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Data\TransactionProfile;

class TransactionProfileTest extends TestCase
{
    public function test_creates_transaction_profile_with_all_properties(): void
    {
        $profile = new TransactionProfile(
            startTime: 1234567890.0,
            endTime: 1234567891.5,
            coroutineId: 123,
            level: 1,
            status: 'committed',
            queryCount: 5,
            connectionName: 'pgsql',
        );

        $this->assertEquals(1234567890.0, $profile->startTime);
        $this->assertEquals(1234567891.5, $profile->endTime);
        $this->assertEquals(123, $profile->coroutineId);
        $this->assertEquals(1, $profile->level);
        $this->assertEquals('committed', $profile->status);
        $this->assertEquals(5, $profile->queryCount);
        $this->assertEquals('pgsql', $profile->connectionName);
    }

    public function test_get_duration_calculates_time_difference(): void
    {
        $profile = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1015.5,
            coroutineId: 1,
            level: 1,
            status: 'committed',
        );

        $this->assertEquals(15.5, $profile->getDuration());
    }

    public function test_get_duration_returns_null_for_active_transaction(): void
    {
        $profile = new TransactionProfile(
            startTime: microtime(true),
            endTime: null,
            coroutineId: 1,
            level: 1,
            status: 'active',
        );

        $this->assertNull($profile->getDuration());
    }

    public function test_is_active_detects_active_transaction(): void
    {
        $activeProfile = new TransactionProfile(
            startTime: microtime(true),
            endTime: null,
            coroutineId: 1,
            level: 1,
            status: 'active',
        );

        $completedProfile = new TransactionProfile(
            startTime: microtime(true) - 10,
            endTime: microtime(true),
            coroutineId: 1,
            level: 1,
            status: 'committed',
        );

        $this->assertTrue($activeProfile->isActive());
        $this->assertFalse($completedProfile->isActive());
    }

    public function test_is_committed_detects_committed_transaction(): void
    {
        $committedProfile = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1010.0,
            coroutineId: 1,
            level: 1,
            status: 'committed',
        );

        $rolledBackProfile = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1010.0,
            coroutineId: 1,
            level: 1,
            status: 'rolled_back',
        );

        $this->assertTrue($committedProfile->isCommitted());
        $this->assertFalse($rolledBackProfile->isCommitted());
    }

    public function test_is_rolled_back_detects_rolled_back_transaction(): void
    {
        $rolledBackProfile = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1010.0,
            coroutineId: 1,
            level: 1,
            status: 'rolled_back',
        );

        $committedProfile = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1010.0,
            coroutineId: 1,
            level: 1,
            status: 'committed',
        );

        $this->assertTrue($rolledBackProfile->isRolledBack());
        $this->assertFalse($committedProfile->isRolledBack());
    }

    public function test_is_long_running_detects_long_transactions(): void
    {
        $longTransaction = new TransactionProfile(
            startTime: 1000.0,
            endTime: 2500.0,
            coroutineId: 1,
            level: 1,
            status: 'committed',
        );

        $shortTransaction = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1050.0,
            coroutineId: 1,
            level: 1,
            status: 'committed',
        );

        $this->assertTrue($longTransaction->isLongRunning(1000.0));
        $this->assertFalse($shortTransaction->isLongRunning(1000.0));
    }

    public function test_with_end_creates_new_instance_with_end_time(): void
    {
        $activeProfile = new TransactionProfile(
            startTime: 1000.0,
            endTime: null,
            coroutineId: 1,
            level: 1,
            status: 'active',
            queryCount: 3,
        );

        $completedProfile = $activeProfile->withEnd(1015.5, 'committed');

        $this->assertNull($activeProfile->endTime);
        $this->assertEquals('active', $activeProfile->status);

        $this->assertEquals(1015.5, $completedProfile->endTime);
        $this->assertEquals('committed', $completedProfile->status);
        $this->assertEquals(3, $completedProfile->queryCount);
    }

    public function test_with_incremented_query_count_creates_new_instance(): void
    {
        $profile = new TransactionProfile(
            startTime: 1000.0,
            endTime: null,
            coroutineId: 1,
            level: 1,
            status: 'active',
            queryCount: 3,
        );

        $updatedProfile = $profile->withIncrementedQueryCount();

        $this->assertEquals(3, $profile->queryCount);
        $this->assertEquals(4, $updatedProfile->queryCount);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $profile = new TransactionProfile(
            startTime: 1000.0,
            endTime: 1015.5,
            coroutineId: 123,
            level: 2,
            status: 'committed',
            queryCount: 7,
            connectionName: 'pgsql',
        );

        $array = $profile->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1000.0, $array['start_time']);
        $this->assertEquals(1015.5, $array['end_time']);
        $this->assertEquals(15.5, $array['duration']);
        $this->assertEquals(123, $array['coroutine_id']);
        $this->assertEquals(2, $array['level']);
        $this->assertEquals('committed', $array['status']);
        $this->assertEquals(7, $array['query_count']);
        $this->assertEquals('pgsql', $array['connection_name']);
        $this->assertFalse($array['is_active']);
        $this->assertTrue($array['is_committed']);
        $this->assertFalse($array['is_rolled_back']);
    }
}
