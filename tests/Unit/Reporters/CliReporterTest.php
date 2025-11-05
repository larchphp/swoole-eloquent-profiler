<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Reporters;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Profiler;
use SwooleProfiler\Reporters\CliReporter;

class CliReporterTest extends TestCase
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

    public function test_report_returns_string(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new CliReporter($profiler);

        $report = $reporter->report();

        $this->assertIsString($report);
    }

    public function test_report_contains_header(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new CliReporter($profiler);

        $report = $reporter->report();

        $this->assertStringContainsString('SWOOLE-ELOQUENT PROFILER REPORT', $report);
    }

    public function test_report_includes_summary_section(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->recordQuery('SELECT 1', [], 10.0);

        $reporter = new CliReporter($profiler);
        $report = $reporter->report();

        $this->assertStringContainsString('Summary', $report);
        $this->assertStringContainsString('Total Queries:', $report);
    }

    public function test_report_includes_query_metrics(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->recordQuery('SELECT 1', [], 10.0);
        $profiler->recordQuery('SELECT 2', [], 20.0);

        $reporter = new CliReporter($profiler);
        $report = $reporter->report();

        $this->assertStringContainsString('Query Metrics', $report);
        $this->assertStringContainsString('Average Duration:', $report);
    }

    public function test_report_shows_slow_queries_section(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->setSlowQueryThreshold(50.0);
        $profiler->recordQuery('SELECT * FROM users', [], 150.0);

        $reporter = new CliReporter($profiler);
        $report = $reporter->report();

        $this->assertStringContainsString('Slow Queries', $report);
        $this->assertStringContainsString('SELECT * FROM users', $report);
    }

    public function test_report_handles_empty_data(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new CliReporter($profiler);

        $report = $reporter->report();

        $this->assertIsString($report);
        $this->assertStringContainsString('SWOOLE-ELOQUENT PROFILER REPORT', $report);
    }
}
