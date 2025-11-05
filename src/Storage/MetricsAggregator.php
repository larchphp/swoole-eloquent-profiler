<?php

declare(strict_types=1);

namespace SwooleProfiler\Storage;

use SwooleProfiler\Data\QueryProfile;
use SwooleProfiler\Data\RequestProfile;

/**
 * Aggregates and calculates statistics from profiling data
 */
class MetricsAggregator
{
    public function __construct(
        private readonly ProfilerStorage $storage
    ) {
    }

    /**
     * Get aggregated metrics
     */
    public function getMetrics(): array
    {
        $queries = $this->storage->getAllQueries();
        $requests = $this->storage->getArchivedRequests();
        $transactions = $this->storage->getAllTransactions();
        $poolSnapshots = $this->storage->getAllPoolSnapshots();

        return [
            'summary' => $this->getSummary($queries, $requests),
            'queries' => $this->getQueryMetrics($queries),
            'requests' => $this->getRequestMetrics($requests),
            'transactions' => $this->getTransactionMetrics($transactions),
            'pool' => $this->getPoolMetrics($poolSnapshots),
        ];
    }

    /**
     * Get overall summary statistics
     */
    private function getSummary(array $queries, array $requests): array
    {
        $successfulQueries = array_filter($queries, fn(QueryProfile $q) => $q->success);
        $failedQueries = array_filter($queries, fn(QueryProfile $q) => !$q->success);

        return [
            'total_queries' => count($queries),
            'successful_queries' => count($successfulQueries),
            'failed_queries' => count($failedQueries),
            'total_requests' => count($requests),
            'total_query_time' => $this->sumQueryDuration($queries),
            'total_pool_wait_time' => $this->sumPoolWaitTime($queries),
        ];
    }

    /**
     * Get query-specific metrics
     */
    private function getQueryMetrics(array $queries): array
    {
        if (empty($queries)) {
            return [
                'total' => 0,
                'avg_duration' => 0,
                'min_duration' => 0,
                'max_duration' => 0,
                'slowest_queries' => [],
                'queries_by_type' => [],
            ];
        }

        $durations = array_map(fn(QueryProfile $q) => $q->duration, $queries);

        return [
            'total' => count($queries),
            'avg_duration' => array_sum($durations) / count($durations),
            'min_duration' => min($durations),
            'max_duration' => max($durations),
            'slowest_queries' => $this->getSlowestQueries($queries, 10),
            'queries_by_type' => $this->groupQueriesByType($queries),
        ];
    }

    /**
     * Get request-specific metrics
     */
    private function getRequestMetrics(array $requests): array
    {
        if (empty($requests)) {
            return [
                'total' => 0,
                'avg_duration' => 0,
                'avg_queries_per_request' => 0,
                'slowest_requests' => [],
            ];
        }

        $durations = array_map(
            fn(RequestProfile $r) => $r->getDuration() ?? 0,
            $requests
        );

        $queryCounts = array_map(
            fn(RequestProfile $r) => $r->getQueryCount(),
            $requests
        );

        return [
            'total' => count($requests),
            'avg_duration' => array_sum($durations) / count($durations),
            'avg_queries_per_request' => array_sum($queryCounts) / count($queryCounts),
            'slowest_requests' => $this->getSlowestRequests($requests, 10),
        ];
    }

    /**
     * Get transaction-specific metrics
     */
    private function getTransactionMetrics(array $transactions): array
    {
        if (empty($transactions)) {
            return [
                'total' => 0,
                'committed' => 0,
                'rolled_back' => 0,
                'avg_duration' => 0,
                'avg_queries_per_transaction' => 0,
            ];
        }

        $committed = array_filter($transactions, fn($t) => $t->isCommitted());
        $rolledBack = array_filter($transactions, fn($t) => $t->isRolledBack());

        $durations = array_map(
            fn($t) => $t->getDuration() ?? 0,
            $transactions
        );

        $queryCounts = array_map(
            fn($t) => $t->queryCount,
            $transactions
        );

        return [
            'total' => count($transactions),
            'committed' => count($committed),
            'rolled_back' => count($rolledBack),
            'avg_duration' => array_sum($durations) / count($durations),
            'avg_queries_per_transaction' => count($queryCounts) > 0
                ? array_sum($queryCounts) / count($queryCounts)
                : 0,
        ];
    }

    /**
     * Get pool-specific metrics
     */
    private function getPoolMetrics(array $snapshots): array
    {
        if (empty($snapshots)) {
            return [
                'total_snapshots' => 0,
                'avg_utilization' => 0,
                'max_utilization' => 0,
                'exhaustion_count' => 0,
            ];
        }

        $utilizations = array_map(
            fn($s) => $s->getUtilization(),
            $snapshots
        );

        $exhaustions = array_filter($snapshots, fn($s) => $s->isExhausted());

        return [
            'total_snapshots' => count($snapshots),
            'avg_utilization' => array_sum($utilizations) / count($utilizations),
            'max_utilization' => max($utilizations),
            'exhaustion_count' => count($exhaustions),
        ];
    }

    /**
     * Get slowest queries
     */
    private function getSlowestQueries(array $queries, int $limit = 10): array
    {
        usort($queries, fn(QueryProfile $a, QueryProfile $b) =>
            $b->duration <=> $a->duration
        );

        return array_map(
            fn(QueryProfile $q) => $q->toArray(),
            array_slice($queries, 0, $limit)
        );
    }

    /**
     * Get slowest requests
     */
    private function getSlowestRequests(array $requests, int $limit = 10): array
    {
        usort($requests, fn(RequestProfile $a, RequestProfile $b) =>
            ($b->getDuration() ?? 0) <=> ($a->getDuration() ?? 0)
        );

        return array_map(
            fn(RequestProfile $r) => $r->toArray(),
            array_slice($requests, 0, $limit)
        );
    }

    /**
     * Group queries by type
     */
    private function groupQueriesByType(array $queries): array
    {
        $grouped = [];

        foreach ($queries as $query) {
            $type = $query->getType();

            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'count' => 0,
                    'total_duration' => 0,
                    'avg_duration' => 0,
                ];
            }

            $grouped[$type]['count']++;
            $grouped[$type]['total_duration'] += $query->duration;
        }

        foreach ($grouped as $type => &$data) {
            $data['avg_duration'] = $data['total_duration'] / $data['count'];
        }

        return $grouped;
    }

    /**
     * Sum total query duration
     */
    private function sumQueryDuration(array $queries): float
    {
        return array_sum(array_map(fn(QueryProfile $q) => $q->duration, $queries));
    }

    /**
     * Sum total pool wait time
     */
    private function sumPoolWaitTime(array $queries): float
    {
        return array_sum(array_map(fn(QueryProfile $q) => $q->poolWaitTime, $queries));
    }
}
