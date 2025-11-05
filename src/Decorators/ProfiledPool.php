<?php

declare(strict_types=1);

namespace SwooleProfiler\Decorators;

use SwooleEloquent\Connection\SwoolePostgresPool;
use SwooleProfiler\Profiler;

/**
 * Wrapper for connection pools that tracks pool metrics
 */
class ProfiledPool
{
    private ?Profiler $profiler = null;

    public function __construct(
        private readonly SwoolePostgresPool $pool,
        ?Profiler $profiler = null,
    ) {
        $this->profiler = $profiler ?? Profiler::getInstance();
    }

    /**
     * Get the underlying pool
     */
    public function getBasePool(): SwoolePostgresPool
    {
        return $this->pool;
    }

    /**
     * Get a connection from the pool with profiling
     */
    public function get(float $timeout = -1): mixed
    {
        $startTime = microtime(true);

        try {
            $connection = $this->pool->get($timeout);
            $waitTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Record pool metrics after getting connection
            $this->recordPoolMetrics();

            // Store wait time in connection context if possible
            // This would require modifying the connection or using coroutine context
            // For now, we'll just track it in the profiler

            return $connection;
        } catch (\Throwable $e) {
            // Record failed pool acquisition
            $this->recordPoolMetrics();
            throw $e;
        }
    }

    /**
     * Return a connection to the pool
     */
    public function put(mixed $connection): void
    {
        $this->pool->put($connection);
        $this->recordPoolMetrics();
    }

    /**
     * Get pool metrics
     */
    public function getMetrics(): array
    {
        return $this->pool->getMetrics();
    }

    /**
     * Close all connections in the pool
     */
    public function close(): void
    {
        $this->pool->close();
    }

    /**
     * Record current pool metrics
     */
    private function recordPoolMetrics(): void
    {
        if (!$this->profiler->isEnabled()) {
            return;
        }

        $metrics = $this->pool->getMetrics();

        $this->profiler->recordPoolMetrics(
            size: $metrics['size'] ?? 0,
            active: $metrics['active'] ?? 0,
            idle: $metrics['idle'] ?? 0,
            waiting: $metrics['waiting'] ?? 0,
            connectionName: null, // Could be enhanced to track connection name
        );
    }

    /**
     * Delegate all other method calls to the base pool
     */
    public function __call($method, $parameters)
    {
        return $this->pool->$method(...$parameters);
    }
}
