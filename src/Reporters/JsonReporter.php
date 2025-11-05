<?php

declare(strict_types=1);

namespace SwooleProfiler\Reporters;

use SwooleProfiler\Profiler;

/**
 * JSON reporter for API responses and monitoring tools
 */
class JsonReporter
{
    public function __construct(
        private readonly Profiler $profiler = new Profiler(),
    ) {
    }

    /**
     * Generate JSON report
     */
    public function report(int $flags = JSON_PRETTY_PRINT): string
    {
        $data = $this->getData();

        return json_encode($data, $flags);
    }

    /**
     * Get report data as array
     */
    public function getData(): array
    {
        $metrics = $this->profiler->getMetrics();

        return [
            'profiler' => [
                'version' => '1.0.0',
                'timestamp' => microtime(true),
                'enabled' => $this->profiler->isEnabled(),
            ],
            'summary' => $metrics['summary'] ?? [],
            'queries' => $this->formatQueryMetrics($metrics['queries'] ?? []),
            'requests' => $metrics['requests'] ?? [],
            'transactions' => $metrics['transactions'] ?? [],
            'pool' => $metrics['pool'] ?? [],
            'slow_queries' => $this->formatSlowQueries(),
        ];
    }

    /**
     * Format query metrics for JSON output
     */
    private function formatQueryMetrics(array $queries): array
    {
        return [
            'total' => $queries['total'] ?? 0,
            'statistics' => [
                'avg_duration' => $queries['avg_duration'] ?? 0,
                'min_duration' => $queries['min_duration'] ?? 0,
                'max_duration' => $queries['max_duration'] ?? 0,
            ],
            'by_type' => $queries['queries_by_type'] ?? [],
        ];
    }

    /**
     * Format slow queries for JSON output
     */
    private function formatSlowQueries(): array
    {
        $slowQueries = $this->profiler->getSlowQueries();

        return array_map(
            fn($query) => $query->toArray(),
            array_slice($slowQueries, 0, 20)
        );
    }

    /**
     * Get report as array (alias for getData)
     */
    public function toArray(): array
    {
        return $this->getData();
    }
}
