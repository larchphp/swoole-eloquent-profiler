<?php

declare(strict_types=1);

namespace SwooleProfiler;

use SwooleProfiler\Data\PoolProfile;
use SwooleProfiler\Data\QueryProfile;
use SwooleProfiler\Data\RequestProfile;
use SwooleProfiler\Storage\MetricsAggregator;
use SwooleProfiler\Storage\ProfilerStorage;

/**
 * Main profiler class for collecting and reporting metrics
 */
class Profiler
{
    private static ?self $instance = null;
    private bool $enabled = true;
    private float $slowQueryThreshold = 100.0; // milliseconds

    private function __construct(
        private readonly ProfilerStorage $storage = new ProfilerStorage(),
        private readonly MetricsAggregator $aggregator = new MetricsAggregator(new ProfilerStorage()),
    ) {
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $storage = new ProfilerStorage();
            self::$instance = new self($storage, new MetricsAggregator($storage));
        }

        return self::$instance;
    }

    /**
     * Enable profiling
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable profiling
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if profiling is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set slow query threshold in milliseconds
     */
    public function setSlowQueryThreshold(float $thresholdMs): void
    {
        $this->slowQueryThreshold = $thresholdMs;
    }

    /**
     * Get slow query threshold
     */
    public function getSlowQueryThreshold(): float
    {
        return $this->slowQueryThreshold;
    }

    /**
     * Start profiling a request
     */
    public function startRequest(?string $path = null, ?string $method = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->storage->startRequest($path, $method);
    }

    /**
     * End profiling for current request
     */
    public function endRequest(): ?RequestProfile
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->storage->endRequest();
    }

    /**
     * Record a query execution
     */
    public function recordQuery(
        string $sql,
        array $bindings,
        float $duration,
        float $poolWaitTime = 0.0,
        bool $success = true,
        ?string $error = null,
        ?int $affectedRows = null,
        ?string $connectionName = null,
    ): void {
        if (!$this->enabled) {
            return;
        }

        $query = new QueryProfile(
            sql: $sql,
            bindings: $bindings,
            duration: $duration,
            poolWaitTime: $poolWaitTime,
            coroutineId: \Swoole\Coroutine::getCid(),
            timestamp: microtime(true),
            success: $success,
            error: $error,
            affectedRows: $affectedRows,
            connectionName: $connectionName,
        );

        $this->storage->recordQuery($query);
    }

    /**
     * Start a transaction
     */
    public function startTransaction(int $level = 1, ?string $connectionName = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->storage->startTransaction($level, $connectionName);
    }

    /**
     * End a transaction
     */
    public function endTransaction(int $level = 1, string $status = 'committed'): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->storage->endTransaction($level, $status);
    }

    /**
     * Increment query count for active transaction
     */
    public function incrementTransactionQueryCount(int $level = 1): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->storage->incrementTransactionQueryCount($level);
    }

    /**
     * Record pool metrics
     */
    public function recordPoolMetrics(
        int $size,
        int $active,
        int $idle,
        int $waiting,
        ?string $connectionName = null,
    ): void {
        if (!$this->enabled) {
            return;
        }

        $pool = new PoolProfile(
            size: $size,
            active: $active,
            idle: $idle,
            waiting: $waiting,
            timestamp: microtime(true),
            connectionName: $connectionName,
        );

        $this->storage->recordPoolMetrics($pool);
    }

    /**
     * Get current request profile
     */
    public function getCurrentRequest(): ?RequestProfile
    {
        return $this->storage->getRequestProfile();
    }

    /**
     * Get all queries
     */
    public function getQueries(): array
    {
        return $this->storage->getAllQueries();
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries(?float $threshold = null): array
    {
        $threshold = $threshold ?? $this->slowQueryThreshold;
        $queries = $this->storage->getAllQueries();

        return array_filter(
            $queries,
            fn(QueryProfile $q) => $q->isSlow($threshold)
        );
    }

    /**
     * Get aggregated metrics
     */
    public function getMetrics(): array
    {
        return $this->aggregator->getMetrics();
    }

    /**
     * Get metrics as JSON
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->getMetrics(), $flags);
    }

    /**
     * Clear all profiling data
     */
    public function clear(): void
    {
        $this->storage->clear();
    }

    /**
     * Get storage instance
     */
    public function getStorage(): ProfilerStorage
    {
        return $this->storage;
    }

    /**
     * Get aggregator instance
     */
    public function getAggregator(): MetricsAggregator
    {
        return $this->aggregator;
    }

    /**
     * Reset the singleton instance (mainly for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
