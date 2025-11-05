<?php

declare(strict_types=1);

namespace SwooleProfiler\Tests\Unit\Reporters;

use PHPUnit\Framework\TestCase;
use SwooleProfiler\Profiler;
use SwooleProfiler\Reporters\HtmlReporter;

class HtmlReporterTest extends TestCase
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

    public function test_report_returns_valid_html(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new HtmlReporter($profiler);

        $html = $reporter->report();

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function test_report_contains_title(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new HtmlReporter($profiler);

        $html = $reporter->report();

        $this->assertStringContainsString('<title>Swoole-Eloquent Profiler Report</title>', $html);
    }

    public function test_report_includes_css_styling(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new HtmlReporter($profiler);

        $html = $reporter->report();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('</style>', $html);
    }

    public function test_report_includes_header_section(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new HtmlReporter($profiler);

        $html = $reporter->report();

        $this->assertStringContainsString('Swoole-Eloquent Profiler Report', $html);
    }

    public function test_report_shows_query_data(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->recordQuery('SELECT * FROM users', [], 10.0);

        $reporter = new HtmlReporter($profiler);
        $html = $reporter->report();

        $this->assertStringContainsString('Query Metrics', $html);
    }

    public function test_report_shows_slow_queries(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->setSlowQueryThreshold(50.0);
        $profiler->recordQuery('SELECT * FROM large_table', [], 150.0);

        $reporter = new HtmlReporter($profiler);
        $html = $reporter->report();

        $this->assertStringContainsString('Slow Queries', $html);
        $this->assertStringContainsString('large_table', $html);
    }

    public function test_report_escapes_html_in_sql(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->recordQuery('SELECT * FROM users WHERE name = "<script>alert(1)</script>"', [], 10.0);

        $reporter = new HtmlReporter($profiler);
        $html = $reporter->report();

        // Should be escaped
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_report_handles_empty_data(): void
    {
        $profiler = Profiler::getInstance();
        $reporter = new HtmlReporter($profiler);

        $html = $reporter->report();

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
    }
}
