<?php

declare(strict_types=1);

namespace SwooleProfiler\Decorators;

use Closure;
use Illuminate\Database\Connection;
use SwooleProfiler\Profiler;

/**
 * Decorator for database connections that adds profiling capabilities
 */
class ProfiledConnection extends Connection
{
    private ?Profiler $profiler = null;
    private int $transactionLevel = 0;

    public function __construct(
        private readonly Connection $connection,
        ?Profiler $profiler = null,
    ) {
        $this->profiler = $profiler ?? Profiler::getInstance();

        // Initialize parent with connection's properties
        $pdo = $this->connection->getPdo();
        $database = $this->connection->getDatabaseName();
        $tablePrefix = $this->connection->getTablePrefix();
        $config = $this->connection->getConfig();

        parent::__construct($pdo, $database, $tablePrefix, $config);

        // Copy other properties
        $this->setQueryGrammar($this->connection->getQueryGrammar());
        $this->setSchemaGrammar($this->connection->getSchemaGrammar());
        $this->setPostProcessor($this->connection->getPostProcessor());

        if ($dispatcher = $this->connection->getEventDispatcher()) {
            $this->setEventDispatcher($dispatcher);
        }
    }

    /**
     * Get the underlying connection
     */
    public function getBaseConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Run a select statement against the database
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        return $this->profileQuery(
            fn() => $this->connection->select($query, $bindings, $useReadPdo),
            $query,
            $bindings
        );
    }

    /**
     * Run a select statement and return a single result
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true): mixed
    {
        return $this->profileQuery(
            fn() => $this->connection->selectOne($query, $bindings, $useReadPdo),
            $query,
            $bindings
        );
    }

    /**
     * Run an insert statement against the database
     */
    public function insert($query, $bindings = []): bool
    {
        return $this->profileQuery(
            fn() => $this->connection->insert($query, $bindings),
            $query,
            $bindings
        );
    }

    /**
     * Run an update statement against the database
     */
    public function update($query, $bindings = []): int
    {
        return $this->profileQuery(
            fn() => $this->connection->update($query, $bindings),
            $query,
            $bindings
        );
    }

    /**
     * Run a delete statement against the database
     */
    public function delete($query, $bindings = []): int
    {
        return $this->profileQuery(
            fn() => $this->connection->delete($query, $bindings),
            $query,
            $bindings
        );
    }

    /**
     * Execute an SQL statement and return the boolean result
     */
    public function statement($query, $bindings = []): bool
    {
        return $this->profileQuery(
            fn() => $this->connection->statement($query, $bindings),
            $query,
            $bindings
        );
    }

    /**
     * Run an SQL statement and get the number of rows affected
     */
    public function affectingStatement($query, $bindings = []): int
    {
        return $this->profileQuery(
            fn() => $this->connection->affectingStatement($query, $bindings),
            $query,
            $bindings
        );
    }

    /**
     * Start a new database transaction
     */
    public function beginTransaction(): void
    {
        $this->transactionLevel++;
        $this->profiler->startTransaction($this->transactionLevel, $this->getName());
        $this->connection->beginTransaction();
    }

    /**
     * Commit the active database transaction
     */
    public function commit(): void
    {
        $this->connection->commit();
        $this->profiler->endTransaction($this->transactionLevel, 'committed');
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    /**
     * Rollback the active database transaction
     */
    public function rollBack($toLevel = null): void
    {
        $this->connection->rollBack($toLevel);
        $this->profiler->endTransaction($this->transactionLevel, 'rolled_back');
        $this->transactionLevel = $toLevel ?? max(0, $this->transactionLevel - 1);
    }

    /**
     * Get the number of active transactions
     */
    public function transactionLevel(): int
    {
        return $this->connection->transactionLevel();
    }

    /**
     * Profile a query execution
     */
    private function profileQuery(Closure $callback, string $query, array $bindings): mixed
    {
        $startTime = microtime(true);
        $success = true;
        $error = null;
        $affectedRows = null;
        $result = null;

        try {
            $result = $callback();

            // Try to get affected rows for mutations
            if (is_int($result)) {
                $affectedRows = $result;
            }

            return $result;
        } catch (\Throwable $e) {
            $success = false;
            $error = $e->getMessage();
            throw $e;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $this->profiler->recordQuery(
                sql: $query,
                bindings: $bindings,
                duration: $duration,
                poolWaitTime: 0.0, // Will be set by pool decorator if used
                success: $success,
                error: $error,
                affectedRows: $affectedRows,
                connectionName: $this->getName(),
            );

            // Increment transaction query count if in transaction
            if ($this->transactionLevel > 0) {
                $this->profiler->incrementTransactionQueryCount($this->transactionLevel);
            }
        }
    }

    /**
     * Delegate all other method calls to the base connection
     */
    public function __call($method, $parameters)
    {
        return $this->connection->$method(...$parameters);
    }
}
