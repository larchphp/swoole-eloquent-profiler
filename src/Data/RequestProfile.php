<?php

declare(strict_types=1);

namespace SwooleProfiler\Data;

/**
 * Represents aggregated profiling metrics for a single request/coroutine
 */
class RequestProfile
{
    /**
     * @param QueryProfile[] $queries
     * @param TransactionProfile[] $transactions
     * @param PoolProfile[] $poolSnapshots
     */
    public function __construct(
        public readonly int $coroutineId,
        public readonly float $startTime,
        public readonly ?float $endTime,
        public readonly array $queries = [],
        public readonly array $transactions = [],
        public readonly array $poolSnapshots = [],
        public readonly ?string $requestPath = null,
        public readonly ?string $requestMethod = null,
    ) {
    }

    /**
     * Get total request duration
     */
    public function getDuration(): ?float
    {
        if ($this->endTime === null) {
            return null;
        }

        return $this->endTime - $this->startTime;
    }

    /**
     * Get total number of queries executed
     */
    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    /**
     * Get total time spent executing queries
     */
    public function getTotalQueryTime(): float
    {
        return array_sum(array_map(
            fn(QueryProfile $q) => $q->duration,
            $this->queries
        ));
    }

    /**
     * Get total time spent waiting for pool connections
     */
    public function getTotalPoolWaitTime(): float
    {
        return array_sum(array_map(
            fn(QueryProfile $q) => $q->poolWaitTime,
            $this->queries
        ));
    }

    /**
     * Get the slowest query
     */
    public function getSlowestQuery(): ?QueryProfile
    {
        if (empty($this->queries)) {
            return null;
        }

        return array_reduce(
            $this->queries,
            fn(?QueryProfile $max, QueryProfile $q) =>
                $max === null || $q->duration > $max->duration ? $q : $max,
            null
        );
    }

    /**
     * Get queries slower than threshold
     */
    public function getSlowQueries(float $thresholdMs = 100.0): array
    {
        return array_filter(
            $this->queries,
            fn(QueryProfile $q) => $q->isSlow($thresholdMs)
        );
    }

    /**
     * Get number of failed queries
     */
    public function getFailedQueryCount(): int
    {
        return count(array_filter(
            $this->queries,
            fn(QueryProfile $q) => !$q->success
        ));
    }

    /**
     * Get query count by type
     */
    public function getQueryCountByType(): array
    {
        $counts = [];

        foreach ($this->queries as $query) {
            $type = $query->getType();
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Check if request is still active
     */
    public function isActive(): bool
    {
        return $this->endTime === null;
    }

    /**
     * Create a new instance with added query
     */
    public function withQuery(QueryProfile $query): self
    {
        return new self(
            $this->coroutineId,
            $this->startTime,
            $this->endTime,
            [...$this->queries, $query],
            $this->transactions,
            $this->poolSnapshots,
            $this->requestPath,
            $this->requestMethod,
        );
    }

    /**
     * Create a new instance with end time
     */
    public function withEnd(float $endTime): self
    {
        return new self(
            $this->coroutineId,
            $this->startTime,
            $endTime,
            $this->queries,
            $this->transactions,
            $this->poolSnapshots,
            $this->requestPath,
            $this->requestMethod,
        );
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'coroutine_id' => $this->coroutineId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration' => $this->getDuration(),
            'request_path' => $this->requestPath,
            'request_method' => $this->requestMethod,
            'query_count' => $this->getQueryCount(),
            'query_count_by_type' => $this->getQueryCountByType(),
            'failed_query_count' => $this->getFailedQueryCount(),
            'total_query_time' => $this->getTotalQueryTime(),
            'total_pool_wait_time' => $this->getTotalPoolWaitTime(),
            'slowest_query' => $this->getSlowestQuery()?->toArray(),
            'slow_queries' => array_map(
                fn(QueryProfile $q) => $q->toArray(),
                $this->getSlowQueries()
            ),
            'is_active' => $this->isActive(),
        ];
    }
}
