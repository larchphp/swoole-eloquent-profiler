<?php

declare(strict_types=1);

namespace SwooleProfiler\Data;

/**
 * Represents a database transaction lifecycle profile
 */
class TransactionProfile
{
    public function __construct(
        public readonly float $startTime,
        public readonly ?float $endTime,
        public readonly int $coroutineId,
        public readonly int $level,
        public readonly string $status,
        public readonly int $queryCount = 0,
        public readonly ?string $connectionName = null,
    ) {
    }

    /**
     * Get transaction duration in milliseconds
     */
    public function getDuration(): ?float
    {
        if ($this->endTime === null) {
            return null;
        }

        return $this->endTime - $this->startTime;
    }

    /**
     * Check if transaction is still active
     */
    public function isActive(): bool
    {
        return $this->endTime === null;
    }

    /**
     * Check if transaction was committed
     */
    public function isCommitted(): bool
    {
        return $this->status === 'committed';
    }

    /**
     * Check if transaction was rolled back
     */
    public function isRolledBack(): bool
    {
        return $this->status === 'rolled_back';
    }

    /**
     * Check if transaction is long-running based on threshold
     */
    public function isLongRunning(float $thresholdMs = 1000.0): bool
    {
        $duration = $this->getDuration();

        return $duration !== null && $duration >= $thresholdMs;
    }

    /**
     * Create a new instance with updated end time and status
     */
    public function withEnd(float $endTime, string $status): self
    {
        return new self(
            $this->startTime,
            $endTime,
            $this->coroutineId,
            $this->level,
            $status,
            $this->queryCount,
            $this->connectionName,
        );
    }

    /**
     * Create a new instance with incremented query count
     */
    public function withIncrementedQueryCount(): self
    {
        return new self(
            $this->startTime,
            $this->endTime,
            $this->coroutineId,
            $this->level,
            $this->status,
            $this->queryCount + 1,
            $this->connectionName,
        );
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration' => $this->getDuration(),
            'coroutine_id' => $this->coroutineId,
            'level' => $this->level,
            'status' => $this->status,
            'query_count' => $this->queryCount,
            'connection_name' => $this->connectionName,
            'is_active' => $this->isActive(),
            'is_committed' => $this->isCommitted(),
            'is_rolled_back' => $this->isRolledBack(),
        ];
    }
}
