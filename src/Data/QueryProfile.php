<?php

declare(strict_types=1);

namespace SwooleProfiler\Data;

/**
 * Represents a single database query execution profile
 */
class QueryProfile
{
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
        public readonly float $duration,
        public readonly float $poolWaitTime,
        public readonly int $coroutineId,
        public readonly float $timestamp,
        public readonly bool $success,
        public readonly ?string $error = null,
        public readonly ?int $affectedRows = null,
        public readonly ?string $connectionName = null,
    ) {
    }

    /**
     * Get total time including pool wait time
     */
    public function getTotalTime(): float
    {
        return $this->duration + $this->poolWaitTime;
    }

    /**
     * Check if this is a slow query based on threshold
     */
    public function isSlow(float $thresholdMs = 100.0): bool
    {
        return $this->duration >= $thresholdMs;
    }

    /**
     * Get query type (SELECT, INSERT, UPDATE, DELETE, etc.)
     */
    public function getType(): string
    {
        $sql = trim(strtoupper($this->sql));

        if (str_starts_with($sql, 'SELECT')) {
            return 'SELECT';
        }
        if (str_starts_with($sql, 'INSERT')) {
            return 'INSERT';
        }
        if (str_starts_with($sql, 'UPDATE')) {
            return 'UPDATE';
        }
        if (str_starts_with($sql, 'DELETE')) {
            return 'DELETE';
        }
        if (str_starts_with($sql, 'BEGIN') || str_starts_with($sql, 'START TRANSACTION')) {
            return 'BEGIN';
        }
        if (str_starts_with($sql, 'COMMIT')) {
            return 'COMMIT';
        }
        if (str_starts_with($sql, 'ROLLBACK')) {
            return 'ROLLBACK';
        }

        return 'OTHER';
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'duration' => $this->duration,
            'pool_wait_time' => $this->poolWaitTime,
            'total_time' => $this->getTotalTime(),
            'coroutine_id' => $this->coroutineId,
            'timestamp' => $this->timestamp,
            'success' => $this->success,
            'error' => $this->error,
            'affected_rows' => $this->affectedRows,
            'connection_name' => $this->connectionName,
            'type' => $this->getType(),
        ];
    }
}
