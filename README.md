# Swoole-Eloquent Profiler

A high-performance, coroutine-aware profiler for the swoole-eloquent library. Track query execution time, connection pool metrics, transaction lifecycles, and more with minimal overhead.

## Features

- **Coroutine-Aware**: Properly isolates profiling data between concurrent coroutines
- **Query Profiling**: Track SQL queries with execution time, bindings, and success/failure status
- **Pool Metrics**: Monitor connection pool utilization, wait times, and exhaustion
- **Transaction Tracking**: Profile transaction duration and query count
- **Request Aggregation**: Aggregate metrics per HTTP request or coroutine
- **Multiple Output Formats**: CLI, JSON, and HTML reports
- **Laravel Integration**: First-class support with service provider and middleware
- **Zero Configuration**: Works out of the box with sensible defaults
- **Minimal Overhead**: Non-blocking design with efficient storage

## Requirements

- PHP 8.1 or higher
- Swoole extension 5.0 or higher
- Laravel 10.x or 11.x (for Laravel integration)

## Installation

```bash
composer require swoole-eloquent/profiler
```

## Quick Start

### Basic Usage

```php
use SwooleEloquent\Connection\SwoolePostgresConnection;
use SwooleEloquent\Connection\SwoolePostgresPool;
use SwooleProfiler\Decorators\ProfiledConnection;
use SwooleProfiler\Decorators\ProfiledPool;
use SwooleProfiler\Profiler;
use SwooleProfiler\Reporters\CliReporter;

// Create your pool and connection
$pool = new SwoolePostgresPool($config, 10);
$connection = new SwoolePostgresConnection($pool, 'pgsql');

// Wrap with profiler decorators
$profiledPool = new ProfiledPool($pool);
$profiledConnection = new ProfiledConnection($connection);

// Use as normal
go(function () use ($profiledConnection) {
    $results = $profiledConnection->select('SELECT * FROM users');

    // Get metrics
    $profiler = Profiler::getInstance();
    $reporter = new CliReporter($profiler);
    echo $reporter->report();
});
```

### Laravel Integration

The profiler automatically registers via package discovery.

#### Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=profiler-config
```

Configure via `.env`:

```env
PROFILER_ENABLED=true
PROFILER_SLOW_QUERY_THRESHOLD=100.0
PROFILER_AUTO_PROFILE_REQUESTS=true
PROFILER_ADD_HEADERS=true
```

#### Usage in Controllers

```php
use SwooleProfiler\Laravel\Facades\Profiler;

class UserController extends Controller
{
    public function index()
    {
        // Queries are automatically profiled
        $users = User::async()->where('active', true)->get();

        // Get current request metrics
        $profile = Profiler::getCurrentRequest();

        return response()->json([
            'users' => $users,
            'queries' => $profile?->getQueryCount(),
            'duration' => $profile?->getTotalQueryTime(),
        ]);
    }

    public function profilerReport()
    {
        return response()->json(Profiler::getMetrics());
    }
}
```

## Architecture

The profiler uses a decorator pattern to wrap connections and pools without modifying the original swoole-eloquent code:

```
┌─────────────────────────────────────────┐
│          Your Application               │
└─────────────────┬───────────────────────┘
                  │
          ┌───────▼────────┐
          │ ProfiledConnection │
          └───────┬────────┘
                  │ (decorates)
          ┌───────▼────────┐
          │ SwooleConnection │
          └───────┬────────┘
                  │
          ┌───────▼────────┐
          │  ProfiledPool   │
          └───────┬────────┘
                  │ (decorates)
          ┌───────▼────────┐
          │   SwoolePool    │
          └─────────────────┘
```

## Components

### Data Classes

- **QueryProfile**: Stores information about a single query execution
- **PoolProfile**: Captures connection pool state at a point in time
- **TransactionProfile**: Tracks transaction lifecycle
- **RequestProfile**: Aggregates metrics for an entire request/coroutine

### Storage

- **ProfilerStorage**: Coroutine-aware storage using `CoroutineContext`
- **MetricsAggregator**: Calculates statistics and aggregates data

### Decorators

- **ProfiledConnection**: Wraps database connections to intercept queries
- **ProfiledPool**: Wraps connection pools to track metrics

### Reporters

- **CliReporter**: Console output with formatted tables
- **JsonReporter**: JSON format for APIs and monitoring tools
- **HtmlReporter**: Web-based dashboard

## Usage Examples

### Profiling a Single Request

```php
$profiler = Profiler::getInstance();

// Start profiling
$profiler->startRequest('/api/users', 'GET');

// Execute queries
$users = $connection->select('SELECT * FROM users WHERE active = ?', [true]);

// End profiling
$profile = $profiler->endRequest();

echo "Queries: " . $profile->getQueryCount() . "\n";
echo "Duration: " . $profile->getTotalQueryTime() . " ms\n";
```

### Getting Slow Queries

```php
$profiler = Profiler::getInstance();

// Set threshold (default: 100ms)
$profiler->setSlowQueryThreshold(50.0);

// Get slow queries
$slowQueries = $profiler->getSlowQueries();

foreach ($slowQueries as $query) {
    echo sprintf(
        "[%.2f ms] %s\n",
        $query->duration,
        $query->sql
    );
}
```

### Transaction Profiling

```php
$profiler = Profiler::getInstance();

$connection->beginTransaction();
// Transaction is automatically tracked

$connection->insert('INSERT INTO users (name) VALUES (?)', ['John']);
$connection->update('UPDATE users SET active = ? WHERE id = ?', [true, 1]);

$connection->commit();
// Transaction end is recorded

// Get transaction metrics
$metrics = $profiler->getMetrics();
print_r($metrics['transactions']);
```

### Multiple Output Formats

```php
use SwooleProfiler\Reporters\CliReporter;
use SwooleProfiler\Reporters\JsonReporter;
use SwooleProfiler\Reporters\HtmlReporter;

$profiler = Profiler::getInstance();

// CLI output
$cliReporter = new CliReporter($profiler);
echo $cliReporter->report();

// JSON output
$jsonReporter = new JsonReporter($profiler);
file_put_contents('profiler.json', $jsonReporter->report());

// HTML output
$htmlReporter = new HtmlReporter($profiler);
file_put_contents('profiler.html', $htmlReporter->report());
```

### HTTP Server Integration

```php
use Swoole\Http\Server;
use SwooleProfiler\Profiler;

$server = new Server('0.0.0.0', 9501);

$server->on('Request', function ($request, $response) {
    $profiler = Profiler::getInstance();

    // Start profiling
    $profiler->startRequest($request->server['request_uri']);

    // Handle request
    $users = $connection->select('SELECT * FROM users');

    // End profiling
    $profile = $profiler->endRequest();

    // Add headers
    $response->header('X-Query-Count', (string)$profile->getQueryCount());
    $response->header('X-Query-Time', sprintf('%.2f', $profile->getTotalQueryTime()));

    $response->end(json_encode(['users' => $users]));
});

$server->start();
```

## Metrics Reference

### Summary Metrics

- `total_queries`: Total number of queries executed
- `successful_queries`: Number of successful queries
- `failed_queries`: Number of failed queries
- `total_requests`: Number of profiled requests
- `total_query_time`: Total time spent executing queries (ms)
- `total_pool_wait_time`: Total time spent waiting for connections (ms)

### Query Metrics

- `avg_duration`: Average query execution time
- `min_duration`: Fastest query time
- `max_duration`: Slowest query time
- `queries_by_type`: Breakdown by query type (SELECT, INSERT, UPDATE, DELETE)

### Pool Metrics

- `avg_utilization`: Average pool utilization percentage
- `max_utilization`: Peak pool utilization
- `exhaustion_count`: Number of times pool was exhausted

### Transaction Metrics

- `total`: Total transactions
- `committed`: Successfully committed transactions
- `rolled_back`: Rolled back transactions
- `avg_duration`: Average transaction duration
- `avg_queries_per_transaction`: Average queries per transaction

## Configuration

### Via Config File (Laravel)

```php
// config/profiler.php
return [
    'enabled' => true,
    'slow_query_threshold' => 100.0,
    'auto_profile_requests' => true,
    'auto_register_middleware' => false,
    'listen_query_events' => false,
    'add_headers' => true,
    'add_to_response' => false,
    'storage' => [
        'max_queries' => 1000,
        'max_requests' => 100,
    ],
];
```

### Via Code

```php
$profiler = Profiler::getInstance();

// Enable/disable
$profiler->enable();
$profiler->disable();

// Set slow query threshold
$profiler->setSlowQueryThreshold(50.0);

// Clear data
$profiler->clear();
```

## Performance Considerations

The profiler is designed for minimal overhead:

- Uses native `microtime(true)` for timing
- Leverages `CoroutineContext` for efficient per-coroutine storage
- Non-blocking operations throughout
- Minimal memory footprint (~2KB per request)

Typical overhead: **< 1% in production environments**

## Best Practices

1. **Enable only in development/staging**: Disable in production or use sampling
2. **Set appropriate thresholds**: Tune slow query threshold based on your needs
3. **Clear data periodically**: Use `$profiler->clear()` to prevent memory growth
4. **Use appropriate reporters**: CLI for development, JSON for monitoring tools
5. **Monitor pool metrics**: Watch for exhaustion warnings

## Troubleshooting

### High Pool Wait Times

Increase connection pool size or optimize query execution time.

```php
// Increase pool size
$pool = new SwoolePostgresPool($config, 20); // Increased from 10
```

### Memory Growth

Clear profiling data periodically:

```php
// Clear after each batch of requests
if ($profiler->getStorage()->getTotalRequestCount() > 100) {
    $profiler->clear();
}
```

### Missing Queries

Ensure you're using the profiled decorators:

```php
// ✗ Wrong
$connection = new SwoolePostgresConnection($pool, 'pgsql');

// ✓ Correct
$baseConnection = new SwoolePostgresConnection($pool, 'pgsql');
$connection = new ProfiledConnection($baseConnection);
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License
