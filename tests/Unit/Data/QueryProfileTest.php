<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Data\QueryProfile;

class QueryProfileTest extends TestCase
{
    public function test_creates_query_profile_with_all_properties(): void
    {
        $profile = new QueryProfile(
            sql: 'SELECT * FROM users WHERE id = ?',
            bindings: [1],
            duration: 15.5,
            poolWaitTime: 2.3,
            coroutineId: 123,
            timestamp: 1234567890.123,
            success: true,
            error: null,
            affectedRows: 1,
            connectionName: 'pgsql',
        );

        $this->assertEquals('SELECT * FROM users WHERE id = ?', $profile->sql);
        $this->assertEquals([1], $profile->bindings);
        $this->assertEquals(15.5, $profile->duration);
        $this->assertEquals(2.3, $profile->poolWaitTime);
        $this->assertEquals(123, $profile->coroutineId);
        $this->assertEquals(1234567890.123, $profile->timestamp);
        $this->assertTrue($profile->success);
        $this->assertNull($profile->error);
        $this->assertEquals(1, $profile->affectedRows);
        $this->assertEquals('pgsql', $profile->connectionName);
    }

    public function test_get_total_time_includes_pool_wait_time(): void
    {
        $profile = new QueryProfile(
            sql: 'SELECT 1',
            bindings: [],
            duration: 10.0,
            poolWaitTime: 5.0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertEquals(15.0, $profile->getTotalTime());
    }

    public function test_is_slow_detects_slow_queries(): void
    {
        $slowQuery = new QueryProfile(
            sql: 'SELECT * FROM large_table',
            bindings: [],
            duration: 150.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $fastQuery = new QueryProfile(
            sql: 'SELECT 1',
            bindings: [],
            duration: 5.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertTrue($slowQuery->isSlow(100.0));
        $this->assertFalse($fastQuery->isSlow(100.0));
    }

    public function test_get_type_detects_select_queries(): void
    {
        $profile = new QueryProfile(
            sql: 'SELECT * FROM users',
            bindings: [],
            duration: 10.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertEquals('SELECT', $profile->getType());
    }

    public function test_get_type_detects_insert_queries(): void
    {
        $profile = new QueryProfile(
            sql: 'INSERT INTO users (name) VALUES (?)',
            bindings: ['John'],
            duration: 10.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertEquals('INSERT', $profile->getType());
    }

    public function test_get_type_detects_update_queries(): void
    {
        $profile = new QueryProfile(
            sql: 'UPDATE users SET active = ? WHERE id = ?',
            bindings: [true, 1],
            duration: 10.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertEquals('UPDATE', $profile->getType());
    }

    public function test_get_type_detects_delete_queries(): void
    {
        $profile = new QueryProfile(
            sql: 'DELETE FROM users WHERE id = ?',
            bindings: [1],
            duration: 10.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertEquals('DELETE', $profile->getType());
    }

    public function test_get_type_detects_transaction_queries(): void
    {
        $beginProfile = new QueryProfile(
            sql: 'BEGIN',
            bindings: [],
            duration: 1.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $commitProfile = new QueryProfile(
            sql: 'COMMIT',
            bindings: [],
            duration: 1.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $rollbackProfile = new QueryProfile(
            sql: 'ROLLBACK',
            bindings: [],
            duration: 1.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );

        $this->assertEquals('BEGIN', $beginProfile->getType());
        $this->assertEquals('COMMIT', $commitProfile->getType());
        $this->assertEquals('ROLLBACK', $rollbackProfile->getType());
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $profile = new QueryProfile(
            sql: 'SELECT * FROM users',
            bindings: [1, 'test'],
            duration: 15.5,
            poolWaitTime: 2.3,
            coroutineId: 123,
            timestamp: 1234567890.123,
            success: true,
            error: null,
            affectedRows: 5,
            connectionName: 'pgsql',
        );

        $array = $profile->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('SELECT * FROM users', $array['sql']);
        $this->assertEquals([1, 'test'], $array['bindings']);
        $this->assertEquals(15.5, $array['duration']);
        $this->assertEquals(2.3, $array['pool_wait_time']);
        $this->assertEquals(17.8, $array['total_time']);
        $this->assertEquals(123, $array['coroutine_id']);
        $this->assertEquals(1234567890.123, $array['timestamp']);
        $this->assertTrue($array['success']);
        $this->assertNull($array['error']);
        $this->assertEquals(5, $array['affected_rows']);
        $this->assertEquals('pgsql', $array['connection_name']);
        $this->assertEquals('SELECT', $array['type']);
    }

    public function test_stores_error_on_failed_query(): void
    {
        $profile = new QueryProfile(
            sql: 'SELECT * FROM nonexistent',
            bindings: [],
            duration: 5.0,
            poolWaitTime: 0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: false,
            error: 'Table does not exist',
        );

        $this->assertFalse($profile->success);
        $this->assertEquals('Table does not exist', $profile->error);
    }
}
