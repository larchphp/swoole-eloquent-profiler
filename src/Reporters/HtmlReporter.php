<?php

declare(strict_types=1);

namespace SwooleProfiler\Reporters;

use SwooleProfiler\Profiler;

/**
 * HTML reporter for web-based profiling dashboard
 */
class HtmlReporter
{
    public function __construct(
        private readonly Profiler $profiler = new Profiler(),
    ) {
    }

    /**
     * Generate HTML report
     */
    public function report(): string
    {
        $metrics = $this->profiler->getMetrics();

        $html = $this->renderHeader();
        $html .= $this->renderSummary($metrics['summary'] ?? []);
        $html .= $this->renderQueryMetrics($metrics['queries'] ?? []);
        $html .= $this->renderPoolMetrics($metrics['pool'] ?? []);
        $html .= $this->renderTransactionMetrics($metrics['transactions'] ?? []);
        $html .= $this->renderSlowQueries();
        $html .= $this->renderFooter();

        return $html;
    }

    /**
     * Render HTML header
     */
    private function renderHeader(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swoole-Eloquent Profiler Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .section { padding: 20px 30px; border-bottom: 1px solid #eee; }
        .section:last-child { border-bottom: none; }
        .section h2 { color: #333; font-size: 20px; margin-bottom: 15px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .metric-card { background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea; }
        .metric-card .label { color: #666; font-size: 13px; margin-bottom: 5px; }
        .metric-card .value { color: #333; font-size: 24px; font-weight: bold; }
        .metric-card .unit { color: #999; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        td { color: #666; }
        .query-item { margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #f59e0b; }
        .query-time { color: #f59e0b; font-weight: bold; }
        .query-sql { color: #333; margin-top: 8px; font-family: 'Courier New', monospace; font-size: 13px; }
        .warning { background: #fef3c7; border-left-color: #f59e0b; color: #92400e; padding: 15px; border-radius: 6px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Swoole-Eloquent Profiler Report</h1>
            <p>Performance analysis and metrics</p>
        </div>
HTML;
    }

    /**
     * Render summary section
     */
    private function renderSummary(array $summary): string
    {
        if (empty($summary)) {
            return '';
        }

        $totalQueries = $summary['total_queries'] ?? 0;
        $successfulQueries = $summary['successful_queries'] ?? 0;
        $failedQueries = $summary['failed_queries'] ?? 0;
        $totalRequests = $summary['total_requests'] ?? 0;
        $totalQueryTime = number_format($summary['total_query_time'] ?? 0, 2);
        $totalPoolWaitTime = number_format($summary['total_pool_wait_time'] ?? 0, 2);

        return <<<HTML
        <div class="section">
            <h2>Summary</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="label">Total Queries</div>
                    <div class="value">{$totalQueries}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Successful Queries</div>
                    <div class="value" style="color: #10b981;">{$successfulQueries}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Failed Queries</div>
                    <div class="value" style="color: #ef4444;">{$failedQueries}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Total Requests</div>
                    <div class="value">{$totalRequests}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Query Time</div>
                    <div class="value">{$totalQueryTime} <span class="unit">ms</span></div>
                </div>
                <div class="metric-card">
                    <div class="label">Pool Wait Time</div>
                    <div class="value">{$totalPoolWaitTime} <span class="unit">ms</span></div>
                </div>
            </div>
        </div>
HTML;
    }

    /**
     * Render query metrics section
     */
    private function renderQueryMetrics(array $queries): string
    {
        if (empty($queries) || ($queries['total'] ?? 0) === 0) {
            return '';
        }

        $avgDuration = number_format($queries['avg_duration'] ?? 0, 2);
        $minDuration = number_format($queries['min_duration'] ?? 0, 2);
        $maxDuration = number_format($queries['max_duration'] ?? 0, 2);

        $html = <<<HTML
        <div class="section">
            <h2>Query Metrics</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="label">Average Duration</div>
                    <div class="value">{$avgDuration} <span class="unit">ms</span></div>
                </div>
                <div class="metric-card">
                    <div class="label">Min Duration</div>
                    <div class="value">{$minDuration} <span class="unit">ms</span></div>
                </div>
                <div class="metric-card">
                    <div class="label">Max Duration</div>
                    <div class="value">{$maxDuration} <span class="unit">ms</span></div>
                </div>
            </div>
HTML;

        if (!empty($queries['queries_by_type'])) {
            $html .= '<table><thead><tr><th>Type</th><th>Count</th><th>Avg Duration</th><th>Total Duration</th></tr></thead><tbody>';

            foreach ($queries['queries_by_type'] as $type => $data) {
                $count = $data['count'] ?? 0;
                $avgDur = number_format($data['avg_duration'] ?? 0, 2);
                $totalDur = number_format($data['total_duration'] ?? 0, 2);

                $html .= "<tr><td>{$type}</td><td>{$count}</td><td>{$avgDur} ms</td><td>{$totalDur} ms</td></tr>";
            }

            $html .= '</tbody></table>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render pool metrics section
     */
    private function renderPoolMetrics(array $pool): string
    {
        if (empty($pool) || ($pool['total_snapshots'] ?? 0) === 0) {
            return '';
        }

        $avgUtil = number_format($pool['avg_utilization'] ?? 0, 2);
        $maxUtil = number_format($pool['max_utilization'] ?? 0, 2);
        $exhaustionCount = $pool['exhaustion_count'] ?? 0;

        $warning = '';
        if ($exhaustionCount > 0) {
            $warning = '<div class="warning">⚠ WARNING: Pool exhaustion detected! Consider increasing pool size.</div>';
        }

        return <<<HTML
        <div class="section">
            <h2>Connection Pool Metrics</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="label">Avg Utilization</div>
                    <div class="value">{$avgUtil} <span class="unit">%</span></div>
                </div>
                <div class="metric-card">
                    <div class="label">Max Utilization</div>
                    <div class="value">{$maxUtil} <span class="unit">%</span></div>
                </div>
                <div class="metric-card">
                    <div class="label">Exhaustion Count</div>
                    <div class="value" style="color: #ef4444;">{$exhaustionCount}</div>
                </div>
            </div>
            {$warning}
        </div>
HTML;
    }

    /**
     * Render transaction metrics section
     */
    private function renderTransactionMetrics(array $transactions): string
    {
        if (empty($transactions) || ($transactions['total'] ?? 0) === 0) {
            return '';
        }

        $total = $transactions['total'] ?? 0;
        $committed = $transactions['committed'] ?? 0;
        $rolledBack = $transactions['rolled_back'] ?? 0;
        $avgDuration = number_format($transactions['avg_duration'] ?? 0, 2);
        $avgQueries = number_format($transactions['avg_queries_per_transaction'] ?? 0, 2);

        return <<<HTML
        <div class="section">
            <h2>Transaction Metrics</h2>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="label">Total Transactions</div>
                    <div class="value">{$total}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Committed</div>
                    <div class="value" style="color: #10b981;">{$committed}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Rolled Back</div>
                    <div class="value" style="color: #ef4444;">{$rolledBack}</div>
                </div>
                <div class="metric-card">
                    <div class="label">Avg Duration</div>
                    <div class="value">{$avgDuration} <span class="unit">ms</span></div>
                </div>
                <div class="metric-card">
                    <div class="label">Avg Queries/Transaction</div>
                    <div class="value">{$avgQueries}</div>
                </div>
            </div>
        </div>
HTML;
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

        $threshold = $this->profiler->getSlowQueryThreshold();
        $html = "<div class=\"section\"><h2>Slow Queries (&gt;{$threshold} ms)</h2>";

        $count = 0;
        foreach ($slowQueries as $query) {
            if ($count >= 10) {
                $remaining = count($slowQueries) - 10;
                $html .= "<p>... and {$remaining} more slow queries</p>";
                break;
            }

            $duration = number_format($query->duration, 2);
            $sql = htmlspecialchars($query->sql);

            $html .= <<<HTML
            <div class="query-item">
                <div class="query-time">{$duration} ms</div>
                <div class="query-sql">{$sql}</div>
            </div>
HTML;
            $count++;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render HTML footer
     */
    private function renderFooter(): string
    {
        return <<<'HTML'
    </div>
</body>
</html>
HTML;
    }
}
