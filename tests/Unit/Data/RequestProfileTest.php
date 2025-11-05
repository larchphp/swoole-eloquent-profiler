<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Data\QueryProfile;
use SwooleProfiler\Data\RequestProfile;

class RequestProfileTest extends TestCase
{
    private function createSampleQuery(string $sql = 'SELECT 1', float $duration = 10.0): QueryProfile
    {
        return new QueryProfile(
            sql: $sql,
            bindings: [],
            duration: $duration,
            poolWaitTime: 1.0,
            coroutineId: 1,
            timestamp: microtime(true),
            success: true,
        );
    }

    public function test_creates_request_profile_with_all_properties(): void
    {
        $queries = [$this->createSampleQuery()];

        $profile = new RequestProfile(
            coroutineId: 123,
            startTime: 1000.0,
            endTime: 1050.5,
            queries: $queries,
            requestPath: '/api/users',
            requestMethod: 'GET',
        );

        $this->assertEquals(123, $profile->coroutineId);
        $this->assertEquals(1000.0, $profile->startTime);
        $this->assertEquals(1050.5, $profile->endTime);
        $this->assertCount(1, $profile->queries);
        $this->assertEquals('/api/users', $profile->requestPath);
        $this->assertEquals('GET', $profile->requestMethod);
    }

    public function test_get_duration_calculates_time_difference(): void
    {
        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: 1000.0,
            endTime: 1025.5,
        );

        $this->assertEquals(25.5, $profile->getDuration());
    }

    public function test_get_duration_returns_null_for_active_request(): void
    {
        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
        );

        $this->assertNull($profile->getDuration());
    }

    public function test_get_query_count_returns_number_of_queries(): void
    {
        $queries = [
            $this->createSampleQuery(),
            $this->createSampleQuery(),
            $this->createSampleQuery(),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $this->assertEquals(3, $profile->getQueryCount());
    }

    public function test_get_total_query_time_sums_all_query_durations(): void
    {
        $queries = [
            $this->createSampleQuery(duration: 10.0),
            $this->createSampleQuery(duration: 15.5),
            $this->createSampleQuery(duration: 8.3),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $this->assertEquals(33.8, $profile->getTotalQueryTime());
    }

    public function test_get_total_pool_wait_time_sums_all_wait_times(): void
    {
        $queries = [
            new QueryProfile('SELECT 1', [], 10.0, 2.0, 1, microtime(true), true),
            new QueryProfile('SELECT 2', [], 10.0, 3.5, 1, microtime(true), true),
            new QueryProfile('SELECT 3', [], 10.0, 1.5, 1, microtime(true), true),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $this->assertEquals(7.0, $profile->getTotalPoolWaitTime());
    }

    public function test_get_slowest_query_returns_query_with_max_duration(): void
    {
        $queries = [
            $this->createSampleQuery(duration: 10.0),
            $this->createSampleQuery(duration: 50.0),
            $this->createSampleQuery(duration: 25.0),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $slowest = $profile->getSlowestQuery();

        $this->assertNotNull($slowest);
        $this->assertEquals(50.0, $slowest->duration);
    }

    public function test_get_slowest_query_returns_null_for_empty_queries(): void
    {
        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: [],
        );

        $this->assertNull($profile->getSlowestQuery());
    }

    public function test_get_slow_queries_filters_by_threshold(): void
    {
        $queries = [
            $this->createSampleQuery(duration: 50.0),
            $this->createSampleQuery(duration: 150.0),
            $this->createSampleQuery(duration: 200.0),
            $this->createSampleQuery(duration: 25.0),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $slowQueries = $profile->getSlowQueries(100.0);

        $this->assertCount(2, $slowQueries);
    }

    public function test_get_failed_query_count_counts_failed_queries(): void
    {
        $queries = [
            new QueryProfile('SELECT 1', [], 10.0, 0, 1, microtime(true), true),
            new QueryProfile('SELECT 2', [], 10.0, 0, 1, microtime(true), false, 'Error'),
            new QueryProfile('SELECT 3', [], 10.0, 0, 1, microtime(true), true),
            new QueryProfile('SELECT 4', [], 10.0, 0, 1, microtime(true), false, 'Error'),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $this->assertEquals(2, $profile->getFailedQueryCount());
    }

    public function test_get_query_count_by_type_groups_queries(): void
    {
        $queries = [
            new QueryProfile('SELECT * FROM users', [], 10.0, 0, 1, microtime(true), true),
            new QueryProfile('SELECT * FROM posts', [], 10.0, 0, 1, microtime(true), true),
            new QueryProfile('INSERT INTO users VALUES (?)', [1], 10.0, 0, 1, microtime(true), true),
            new QueryProfile('UPDATE users SET active = ?', [true], 10.0, 0, 1, microtime(true), true),
            new QueryProfile('DELETE FROM users WHERE id = ?', [1], 10.0, 0, 1, microtime(true), true),
        ];

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: $queries,
        );

        $counts = $profile->getQueryCountByType();

        $this->assertEquals(2, $counts['SELECT']);
        $this->assertEquals(1, $counts['INSERT']);
        $this->assertEquals(1, $counts['UPDATE']);
        $this->assertEquals(1, $counts['DELETE']);
    }

    public function test_is_active_detects_active_request(): void
    {
        $activeProfile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
        );

        $completedProfile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true) - 10,
            endTime: microtime(true),
        );

        $this->assertTrue($activeProfile->isActive());
        $this->assertFalse($completedProfile->isActive());
    }

    public function test_with_query_creates_new_instance_with_added_query(): void
    {
        $query1 = $this->createSampleQuery();
        $query2 = $this->createSampleQuery();

        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: microtime(true),
            endTime: null,
            queries: [$query1],
        );

        $updatedProfile = $profile->withQuery($query2);

        $this->assertCount(1, $profile->queries);
        $this->assertCount(2, $updatedProfile->queries);
    }

    public function test_with_end_creates_new_instance_with_end_time(): void
    {
        $profile = new RequestProfile(
            coroutineId: 1,
            startTime: 1000.0,
            endTime: null,
        );

        $completedProfile = $profile->withEnd(1050.5);

        $this->assertNull($profile->endTime);
        $this->assertEquals(1050.5, $completedProfile->endTime);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $queries = [
            $this->createSampleQuery(duration: 10.0),
            $this->createSampleQuery(duration: 150.0),
        ];

        $profile = new RequestProfile(
            coroutineId: 123,
            startTime: 1000.0,
            endTime: 1050.5,
            queries: $queries,
            requestPath: '/api/users',
            requestMethod: 'GET',
        );

        $array = $profile->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(123, $array['coroutine_id']);
        $this->assertEquals(1000.0, $array['start_time']);
        $this->assertEquals(1050.5, $array['end_time']);
        $this->assertEquals(50.5, $array['duration']);
        $this->assertEquals('/api/users', $array['request_path']);
        $this->assertEquals('GET', $array['request_method']);
        $this->assertEquals(2, $array['query_count']);
        $this->assertEquals(0, $array['failed_query_count']);
        $this->assertArrayHasKey('query_count_by_type', $array);
        $this->assertArrayHasKey('slowest_query', $array);
        $this->assertArrayHasKey('slow_queries', $array);
        $this->assertFalse($array['is_active']);
    }
}
