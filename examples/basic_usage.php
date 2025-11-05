<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SwooleEloquent\Connection\SwoolePostgresConnection;
use SwooleEloquent\Connection\SwoolePostgresPool;
use SwooleEloquent\ORM\AsyncModel;
use SwooleProfiler\Decorators\ProfiledConnection;
use SwooleProfiler\Decorators\ProfiledPool;
use SwooleProfiler\Profiler;
use SwooleProfiler\Reporters\CliReporter;

// Define a sample model
class User extends AsyncModel
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email'];
}

// Create connection pool
$poolConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int)(getenv('DB_PORT') ?: 5432),
    'database' => getenv('DB_NAME') ?: 'test_db',
    'username' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
];

$poolSize = 10;
$basePool = new SwoolePostgresPool($poolConfig, $poolSize);

// Wrap pool with profiler
$pool = new ProfiledPool($basePool);

// Create connection
$baseConnection = new SwoolePostgresConnection($basePool, 'pgsql');

// Wrap connection with profiler
$connection = new ProfiledConnection($baseConnection);

// Get profiler instance
$profiler = Profiler::getInstance();
$profiler->enable();
$profiler->setSlowQueryThreshold(50.0); // 50ms

echo "=== Swoole-Eloquent Profiler - Basic Usage Example ===\n\n";

// Example 1: Simple query profiling
echo "Running example queries...\n";

go(function () use ($connection, $profiler) {
    // Start profiling this request
    $profiler->startRequest('/users', 'GET');

    try {
        // Execute some queries
        $results = $connection->select('SELECT * FROM users WHERE active = ?', [true]);
        echo "Query 1: Selected " . count($results) . " users\n";

        $connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', [
            'John Doe',
            'john@example.com'
        ]);
        echo "Query 2: Inserted new user\n";

        $connection->update('UPDATE users SET active = ? WHERE id = ?', [true, 1]);
        echo "Query 3: Updated user\n";

    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // End profiling
    $requestProfile = $profiler->endRequest();

    if ($requestProfile) {
        echo "\nRequest Profile:\n";
        echo "  Total Queries: " . $requestProfile->getQueryCount() . "\n";
        echo "  Total Query Time: " . sprintf('%.2f ms', $requestProfile->getTotalQueryTime()) . "\n";
        echo "  Pool Wait Time: " . sprintf('%.2f ms', $requestProfile->getTotalPoolWaitTime()) . "\n";

        if ($slowest = $requestProfile->getSlowestQuery()) {
            echo "  Slowest Query: " . sprintf('%.2f ms', $slowest->duration) . "\n";
        }
    }
});

// Example 2: Transaction profiling
echo "\n\nRunning transaction example...\n";

go(function () use ($connection, $profiler) {
    $profiler->startRequest('/users/transaction', 'POST');

    try {
        $connection->beginTransaction();

        $connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', [
            'Jane Smith',
            'jane@example.com'
        ]);

        $connection->update('UPDATE users SET verified = ? WHERE email = ?', [
            true,
            'jane@example.com'
        ]);

        $connection->commit();
        echo "Transaction committed successfully\n";

    } catch (\Throwable $e) {
        $connection->rollBack();
        echo "Transaction rolled back: " . $e->getMessage() . "\n";
    }

    $profiler->endRequest();
});

// Example 3: Multiple concurrent requests
echo "\n\nRunning concurrent queries example...\n";

for ($i = 1; $i <= 5; $i++) {
    go(function () use ($connection, $profiler, $i) {
        $profiler->startRequest("/users/{$i}", 'GET');

        try {
            $results = $connection->select('SELECT * FROM users WHERE id = ?', [$i]);
            echo "Coroutine {$i}: Found " . count($results) . " users\n";
        } catch (\Throwable $e) {
            echo "Coroutine {$i}: Error - " . $e->getMessage() . "\n";
        }

        $profiler->endRequest();
    });
}

// Wait for all coroutines to complete
\Swoole\Event::wait();

// Generate and display report
echo "\n\n";
echo str_repeat('=', 70) . "\n";
echo "PROFILING REPORT\n";
echo str_repeat('=', 70) . "\n\n";

$reporter = new CliReporter($profiler);
echo $reporter->report();

echo "\n\n";

// Show individual query details
echo "=== Query Details ===\n";
$queries = $profiler->getQueries();
foreach ($queries as $i => $query) {
    echo sprintf(
        "%d. [%.2f ms] %s\n",
        $i + 1,
        $query->duration,
        $query->sql
    );
}

// Show slow queries
$slowQueries = $profiler->getSlowQueries();
if (!empty($slowQueries)) {
    echo "\n=== Slow Queries ===\n";
    foreach ($slowQueries as $query) {
        echo sprintf(
            "  [%.2f ms] %s\n",
            $query->duration,
            $query->sql
        );
    }
} else {
    echo "\nNo slow queries detected!\n";
}

// Get metrics as JSON
echo "\n=== JSON Export ===\n";
echo $profiler->toJson();

// Cleanup
$profiler->clear();
$pool->close();

echo "\n\nExample completed!\n";
