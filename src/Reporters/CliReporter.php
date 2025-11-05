<?php

declare(strict_types=1);

namespace SwooleProfiler\Reporters;

use SwooleProfiler\Profiler;

/**
 * CLI reporter for console output with formatted tables
 */
class CliReporter
{
    public function __construct(
        private readonly Profiler $profiler = new Profiler(),
    ) {
    }

    /**
     * Generate and output the report
     */
    public function report(): string
    {
        $metrics = $this->profiler->getMetrics();
        $output = [];

        $output[] = $this->renderHeader();
        $output[] = $this->renderSummary($metrics['summary'] ?? []);
        $output[] = $this->renderQueryMetrics($metrics['queries'] ?? []);
        $output[] = $this->renderPoolMetrics($metrics['pool'] ?? []);
        $output[] = $this->renderTransactionMetrics($metrics['transactions'] ?? []);
        $output[] = $this->renderSlowQueries();

        return implode("\n\n", array_filter($output));
    }

    /**
     * Render header
     */
    private function renderHeader(): string
    {
        $lines = [];
        $lines[] = str_repeat('=', 70);
        $lines[] = $this->center('SWOOLE-ELOQUENT PROFILER REPORT', 70);
        $lines[] = str_repeat('=', 70);

        return implode("\n", $lines);
    }

    /**
     * Render summary section
     */
    private function renderSummary(array $summary): string
    {
        if (empty($summary)) {
            return '';
        }

        $lines = [];
        $lines[] = $this->sectionHeader('Summary');
        $lines[] = sprintf('  Total Queries:          %d', $summary['total_queries'] ?? 0);
        $lines[] = sprintf('  Successful Queries:     %d', $summary['successful_queries'] ?? 0);
        $lines[] = sprintf('  Failed Queries:         %d', $summary['failed_queries'] ?? 0);
        $lines[] = sprintf('  Total Requests:         %d', $summary['total_requests'] ?? 0);
        $lines[] = sprintf('  Total Query Time:       %.2f ms', $summary['total_query_time'] ?? 0);
        $lines[] = sprintf('  Total Pool Wait Time:   %.2f ms', $summary['total_pool_wait_time'] ?? 0);

        return implode("\n", $lines);
    }

    /**
     * Render query metrics section
     */
    private function renderQueryMetrics(array $queries): string
    {
        if (empty($queries) || ($queries['total'] ?? 0) === 0) {
            return '';
        }

        $lines = [];
        $lines[] = $this->sectionHeader('Query Metrics');
        $lines[] = sprintf('  Total Queries:          %d', $queries['total'] ?? 0);
        $lines[] = sprintf('  Average Duration:       %.2f ms', $queries['avg_duration'] ?? 0);
        $lines[] = sprintf('  Min Duration:           %.2f ms', $queries['min_duration'] ?? 0);
        $lines[] = sprintf('  Max Duration:           %.2f ms', $queries['max_duration'] ?? 0);

        if (!empty($queries['queries_by_type'])) {
            $lines[] = '';
            $lines[] = '  Queries by Type:';

            foreach ($queries['queries_by_type'] as $type => $data) {
                $lines[] = sprintf(
                    '    %-10s  Count: %4d  Avg: %7.2f ms  Total: %7.2f ms',
                    $type,
                    $data['count'] ?? 0,
                    $data['avg_duration'] ?? 0,
                    $data['total_duration'] ?? 0
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render pool metrics section
     */
    private function renderPoolMetrics(array $pool): string
    {
        if (empty($pool) || ($pool['total_snapshots'] ?? 0) === 0) {
            return '';
        }

        $lines = [];
        $lines[] = $this->sectionHeader('Connection Pool Metrics');
        $lines[] = sprintf('  Total Snapshots:        %d', $pool['total_snapshots'] ?? 0);
        $lines[] = sprintf('  Average Utilization:    %.2f%%', $pool['avg_utilization'] ?? 0);
        $lines[] = sprintf('  Max Utilization:        %.2f%%', $pool['max_utilization'] ?? 0);
        $lines[] = sprintf('  Exhaustion Count:       %d', $pool['exhaustion_count'] ?? 0);

        if (($pool['exhaustion_count'] ?? 0) > 0) {
            $lines[] = '';
            $lines[] = '  ⚠ WARNING: Pool exhaustion detected! Consider increasing pool size.';
        }

        return implode("\n", $lines);
    }

    /**
     * Render transaction metrics section
     */
    private function renderTransactionMetrics(array $transactions): string
    {
        if (empty($transactions) || ($transactions['total'] ?? 0) === 0) {
            return '';
        }

        $lines = [];
        $lines[] = $this->sectionHeader('Transaction Metrics');
        $lines[] = sprintf('  Total Transactions:     %d', $transactions['total'] ?? 0);
        $lines[] = sprintf('  Committed:              %d', $transactions['committed'] ?? 0);
        $lines[] = sprintf('  Rolled Back:            %d', $transactions['rolled_back'] ?? 0);
        $lines[] = sprintf('  Average Duration:       %.2f ms', $transactions['avg_duration'] ?? 0);
        $lines[] = sprintf(
            '  Avg Queries/Transaction: %.2f',
            $transactions['avg_queries_per_transaction'] ?? 0
        );

        return implode("\n", $lines);
    }

    /**
     * Render slow queries section
     */
    private function renderSlowQueries(): string
    {
        $slowQueries = $this->profiler->getSlowQueries();

        if (empty($slowQueries)) {
            return '';
        }

        $lines = [];
        $lines[] = $this->sectionHeader(
            sprintf('Slow Queries (>%.0f ms)', $this->profiler->getSlowQueryThreshold())
        );

        $count = 0;
        foreach ($slowQueries as $query) {
            if ($count >= 10) {
                $remaining = count($slowQueries) - 10;
                $lines[] = sprintf('  ... and %d more slow queries', $remaining);
                break;
            }

            $lines[] = sprintf(
                '  [%.2f ms] %s',
                $query->duration,
                $this->truncate($query->sql, 100)
            );

            if (!empty($query->bindings)) {
                $lines[] = sprintf(
                    '            Bindings: %s',
                    $this->formatBindings($query->bindings)
                );
            }

            $count++;
        }

        return implode("\n", $lines);
    }

    /**
     * Render section header
     */
    private function sectionHeader(string $title): string
    {
        return sprintf("\n%s\n%s", $title, str_repeat('-', strlen($title)));
    }

    /**
     * Center text within a width
     */
    private function center(string $text, int $width): string
    {
        $padding = max(0, $width - strlen($text));
        $leftPadding = (int)floor($padding / 2);
        $rightPadding = $padding - $leftPadding;

        return str_repeat(' ', $leftPadding) . $text . str_repeat(' ', $rightPadding);
    }

    /**
     * Truncate text to a maximum length
     */
    private function truncate(string $text, int $length): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }

    /**
     * Format bindings for display
     */
    private function formatBindings(array $bindings): string
    {
        $formatted = array_map(function ($binding) {
            if (is_string($binding)) {
                return '"' . addslashes($binding) . '"';
            }
            if (is_null($binding)) {
                return 'NULL';
            }
            if (is_bool($binding)) {
                return $binding ? 'TRUE' : 'FALSE';
            }

            return (string)$binding;
        }, $bindings);

        return implode(', ', $formatted);
    }
}
