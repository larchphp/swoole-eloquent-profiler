<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Reporters;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Profiler;
use SwooleProfiler\Reporters\JsonReporter;

class JsonReporterTest extends TestCase
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

    public function test_report_returns_valid_json(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new JsonReporter($profiler);

        $json = $reporter->report();

        $this->assertIsString($json);
        $this->assertNotNull(json_decode($json));
    }

    public function test_get_data_returns_array_with_required_keys(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new JsonReporter($profiler);

        $data = $reporter->getData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('profiler', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('queries', $data);
        $this->assertArrayHasKey('requests', $data);
        $this->assertArrayHasKey('transactions', $data);
        $this->assertArrayHasKey('pool', $data);
        $this->assertArrayHasKey('slow_queries', $data);
    }

    public function test_profiler_section_contains_metadata(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new JsonReporter($profiler);

        $data = $reporter->getData();

        $this->assertArrayHasKey('version', $data['profiler']);
        $this->assertArrayHasKey('timestamp', $data['profiler']);
        $this->assertArrayHasKey('enabled', $data['profiler']);
    }

    public function test_to_array_is_alias_for_get_data(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new JsonReporter($profiler);

        $data1 = $reporter->getData();
        $data2 = $reporter->toArray();

        $this->assertEquals($data1, $data2);
    }

    public function test_report_formats_with_pretty_print_by_default(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->recordQuery('SELECT 1', [], 10.0);

        $reporter = new JsonReporter($profiler);
        $json = $reporter->report();

        // Pretty printed JSON has newlines
        $this->assertStringContainsString("\n", $json);
    }

    public function test_report_can_format_without_pretty_print(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->recordQuery('SELECT 1', [], 10.0);

        $reporter = new JsonReporter($profiler);
        $json = $reporter->report(0);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }

    public function test_includes_slow_queries_in_output(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->setSlowQueryThreshold(50.0);
        $profiler->recordQuery('SELECT * FROM users', [], 150.0);

        $reporter = new JsonReporter($profiler);
        $data = $reporter->getData();

        $this->assertIsArray($data['slow_queries']);
        $this->assertNotEmpty($data['slow_queries']);
    }
}
