<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Profiler;

class ProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Profiler::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Profiler::reset();
    }

    public function test_get_instance_returns_singleton(): void
    {
        $instance1 = Profiler::getInstance();
        $instance2 = Profiler::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_enable_and_disable_controls_profiling_state(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->enable();
        $this->assertTrue($profiler->isEnabled());

        $profiler->disable();
        $this->assertFalse($profiler->isEnabled());
    }

    public function test_is_enabled_by_default(): void
    {
        $profiler = Profiler::getInstance();

        $this->assertTrue($profiler->isEnabled());
    }

    public function test_set_slow_query_threshold_updates_threshold(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->setSlowQueryThreshold(50.0);

        $this->assertEquals(50.0, $profiler->getSlowQueryThreshold());
    }

    public function test_default_slow_query_threshold_is_100ms(): void
    {
        $profiler = Profiler::getInstance();

        $this->assertEquals(100.0, $profiler->getSlowQueryThreshold());
    }

    public function test_record_query_stores_query_when_enabled(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->enable();

        $profiler->recordQuery(
            sql: 'SELECT * FROM users',
            bindings: [1],
            duration: 15.5,
            poolWaitTime: 2.0,
            success: true,
        );

        $queries = $profiler->getQueries();

        $this->assertCount(1, $queries);
        $this->assertEquals('SELECT * FROM users', $queries[0]->sql);
        $this->assertEquals(15.5, $queries[0]->duration);
    }

    public function test_record_query_does_nothing_when_disabled(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->disable();

        $profiler->recordQuery(
            sql: 'SELECT * FROM users',
            bindings: [],
            duration: 15.5,
        );

        $queries = $profiler->getQueries();

        $this->assertCount(0, $queries);
    }

    public function test_get_slow_queries_filters_by_threshold(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->setSlowQueryThreshold(50.0);

        $profiler->recordQuery('SELECT 1', [], 10.0);
        $profiler->recordQuery('SELECT 2', [], 60.0);
        $profiler->recordQuery('SELECT 3', [], 100.0);
        $profiler->recordQuery('SELECT 4', [], 25.0);

        $slowQueries = $profiler->getSlowQueries();

        $this->assertCount(2, $slowQueries);
    }

    public function test_get_slow_queries_accepts_custom_threshold(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->recordQuery('SELECT 1', [], 10.0);
        $profiler->recordQuery('SELECT 2', [], 30.0);
        $profiler->recordQuery('SELECT 3', [], 50.0);

        $slowQueries = $profiler->getSlowQueries(25.0);

        $this->assertCount(2, $slowQueries);
    }

    public function test_clear_removes_all_profiling_data(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->recordQuery('SELECT 1', [], 10.0);
        $profiler->recordQuery('SELECT 2', [], 20.0);

        $this->assertCount(2, $profiler->getQueries());

        $profiler->clear();

        $this->assertCount(0, $profiler->getQueries());
    }

    public function test_to_json_returns_json_string(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->recordQuery('SELECT 1', [], 10.0);

        $json = $profiler->toJson();

        $this->assertIsString($json);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('queries', $data);
    }

    public function test_get_metrics_returns_aggregated_data(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->recordQuery('SELECT 1', [], 10.0);
        $profiler->recordQuery('INSERT INTO users VALUES (?)', [1], 15.0);

        $metrics = $profiler->getMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('summary', $metrics);
        $this->assertArrayHasKey('queries', $metrics);
        $this->assertArrayHasKey('requests', $metrics);
        $this->assertArrayHasKey('transactions', $metrics);
        $this->assertArrayHasKey('pool', $metrics);
    }

    public function test_record_pool_metrics_stores_pool_snapshot(): void
    {
        $profiler = Profiler::getInstance();

        $profiler->recordPoolMetrics(
            size: 10,
            active: 5,
            idle: 5,
            waiting: 0,
        );

        $metrics = $profiler->getMetrics();

        $this->assertEquals(1, $metrics['pool']['total_snapshots']);
    }

    public function test_record_pool_metrics_does_nothing_when_disabled(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->disable();

        $profiler->recordPoolMetrics(
            size: 10,
            active: 5,
            idle: 5,
            waiting: 0,
        );

        $metrics = $profiler->getMetrics();

        $this->assertEquals(0, $metrics['pool']['total_snapshots']);
    }

    public function test_reset_clears_singleton_instance(): void
    {
        $instance1 = Profiler::getInstance();
        $instance1->recordQuery('SELECT 1', [], 10.0);

        Profiler::reset();

        $instance2 = Profiler::getInstance();

        $this->assertNotSame($instance1, $instance2);
        $this->assertCount(0, $instance2->getQueries());
    }
}
