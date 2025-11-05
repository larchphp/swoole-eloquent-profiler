<?php

declare(strict_types=1);

namespace SwooleProfiler\Data;

/**
 * Represents connection pool metrics at a point in time
 */
class PoolProfile
{
    public function __construct(
        public readonly int $size,
        public readonly int $active,
        public readonly int $idle,
        public readonly int $waiting,
        public readonly float $timestamp,
        public readonly ?string $connectionName = null,
    ) {
    }

    /**
     * Check if pool is exhausted (all connections in use)
     */
    public function isExhausted(): bool
    {
        return $this->idle === 0 && $this->active === $this->size;
    }

    /**
     * Check if pool is underutilized
     */
    public function isUnderutilized(float $threshold = 0.3): bool
    {
        if ($this->size === 0) {
            return false;
        }

        return ($this->active / $this->size) < $threshold;
    }

    /**
     * Get pool utilization percentage
     */
    public function getUtilization(): float
    {
        if ($this->size === 0) {
            return 0.0;
        }

        return ($this->active / $this->size) * 100;
    }

    /**
     * Check if there are coroutines waiting for connections
     */
    public function hasWaiting(): bool
    {
        return $this->waiting > 0;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'active' => $this->active,
            'idle' => $this->idle,
            'waiting' => $this->waiting,
            'timestamp' => $this->timestamp,
            'connection_name' => $this->connectionName,
            'utilization' => $this->getUtilization(),
            'is_exhausted' => $this->isExhausted(),
            'has_waiting' => $this->hasWaiting(),
        ];
    }
}
