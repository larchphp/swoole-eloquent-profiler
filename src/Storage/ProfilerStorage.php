<?php

declare(strict_types=1);

namespace SwooleProfiler\Storage;

use SwooleEloquent\Support\CoroutineContext;
use SwooleProfiler\Data\PoolProfile;
use SwooleProfiler\Data\QueryProfile;
use SwooleProfiler\Data\RequestProfile;
use SwooleProfiler\Data\TransactionProfile;

/**
 * Coroutine-aware storage for profiling data
 */
class ProfilerStorage
{
    private const CONTEXT_KEY = '__profiler_data__';
    private const GLOBAL_KEY = '__profiler_global__';

    /**
     * Global storage for cross-coroutine data
     */
    private static array $globalStorage = [];

    /**
     * Start profiling for the current coroutine
     */
    public function startRequest(?string $path = null, ?string $method = null): void
    {
        $coroutineId = $this->getCoroutineId();
        $startTime = microtime(true);

        $profile = new RequestProfile(
            coroutineId: $coroutineId,
            startTime: $startTime,
            endTime: null,
            requestPath: $path,
            requestMethod: $method,
        );

        $this->setRequestProfile($profile);
    }

    /**
     * End profiling for the current coroutine
     */
    public function endRequest(): ?RequestProfile
    {
        $profile = $this->getRequestProfile();

        if ($profile === null) {
            return null;
        }

        $endTime = microtime(true);
        $completedProfile = $profile->withEnd($endTime);

        $this->setRequestProfile($completedProfile);
        $this->archiveRequestProfile($completedProfile);

        return $completedProfile;
    }

    /**
     * Record a query execution
     */
    public function recordQuery(QueryProfile $query): void
    {
        $profile = $this->getRequestProfile();

        if ($profile === null) {
            // Create an implicit request profile if none exists
            $this->startRequest();
            $profile = $this->getRequestProfile();
        }

        $updatedProfile = $profile->withQuery($query);
        $this->setRequestProfile($updatedProfile);

        // Also store in global queries list
        $this->appendToGlobal('queries', $query);
    }

    /**
     * Start a transaction
     */
    public function startTransaction(int $level, ?string $connectionName = null): void
    {
        $coroutineId = $this->getCoroutineId();
        $startTime = microtime(true);

        $transaction = new TransactionProfile(
            startTime: $startTime,
            endTime: null,
            coroutineId: $coroutineId,
            level: $level,
            status: 'active',
            connectionName: $connectionName,
        );

        $key = $this->getTransactionKey($level);
        CoroutineContext::put($key, $transaction);
    }

    /**
     * End a transaction
     */
    public function endTransaction(int $level, string $status): ?TransactionProfile
    {
        $key = $this->getTransactionKey($level);
        $transaction = CoroutineContext::get($key);

        if (!$transaction instanceof TransactionProfile) {
            return null;
        }

        $endTime = microtime(true);
        $completedTransaction = $transaction->withEnd($endTime, $status);

        CoroutineContext::forget($key);
        $this->appendToGlobal('transactions', $completedTransaction);

        return $completedTransaction;
    }

    /**
     * Increment query count for active transaction
     */
    public function incrementTransactionQueryCount(int $level): void
    {
        $key = $this->getTransactionKey($level);
        $transaction = CoroutineContext::get($key);

        if ($transaction instanceof TransactionProfile) {
            $updated = $transaction->withIncrementedQueryCount();
            CoroutineContext::put($key, $updated);
        }
    }

    /**
     * Record pool metrics snapshot
     */
    public function recordPoolMetrics(PoolProfile $pool): void
    {
        $this->appendToGlobal('pool_snapshots', $pool);
    }

    /**
     * Get current request profile
     */
    public function getRequestProfile(): ?RequestProfile
    {
        $data = CoroutineContext::get(self::CONTEXT_KEY);

        return $data instanceof RequestProfile ? $data : null;
    }

    /**
     * Get all archived request profiles
     */
    public function getArchivedRequests(): array
    {
        return self::$globalStorage['archived_requests'] ?? [];
    }

    /**
     * Get all recorded queries globally
     */
    public function getAllQueries(): array
    {
        return self::$globalStorage['queries'] ?? [];
    }

    /**
     * Get all recorded transactions globally
     */
    public function getAllTransactions(): array
    {
        return self::$globalStorage['transactions'] ?? [];
    }

    /**
     * Get all pool snapshots globally
     */
    public function getAllPoolSnapshots(): array
    {
        return self::$globalStorage['pool_snapshots'] ?? [];
    }

    /**
     * Clear all profiling data
     */
    public function clear(): void
    {
        CoroutineContext::clear();
        self::$globalStorage = [];
    }

    /**
     * Clear data for current coroutine only
     */
    public function clearCurrent(): void
    {
        CoroutineContext::forget(self::CONTEXT_KEY);
    }

    /**
     * Get total number of recorded queries
     */
    public function getTotalQueryCount(): int
    {
        return count($this->getAllQueries());
    }

    /**
     * Get total number of archived requests
     */
    public function getTotalRequestCount(): int
    {
        return count($this->getArchivedRequests());
    }

    /**
     * Set request profile in coroutine context
     */
    private function setRequestProfile(RequestProfile $profile): void
    {
        CoroutineContext::put(self::CONTEXT_KEY, $profile);
    }

    /**
     * Archive completed request profile to global storage
     */
    private function archiveRequestProfile(RequestProfile $profile): void
    {
        $this->appendToGlobal('archived_requests', $profile);
    }

    /**
     * Append data to global storage array
     */
    private function appendToGlobal(string $key, mixed $value): void
    {
        if (!isset(self::$globalStorage[$key])) {
            self::$globalStorage[$key] = [];
        }

        self::$globalStorage[$key][] = $value;
    }

    /**
     * Get coroutine ID
     */
    private function getCoroutineId(): int
    {
        return \Swoole\Coroutine::getCid();
    }

    /**
     * Get transaction context key
     */
    private function getTransactionKey(int $level): string
    {
        return sprintf('__transaction_%d__', $level);
    }
}
