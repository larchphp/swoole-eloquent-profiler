<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use SwooleEloquent\Connection\SwoolePostgresConnection;
use SwooleEloquent\Connection\SwoolePostgresPool;
use SwooleEloquent\ORM\AsyncModel;
use SwooleProfiler\Decorators\ProfiledConnection;
use SwooleProfiler\Decorators\ProfiledPool;
use SwooleProfiler\Profiler;
use SwooleProfiler\Reporters\JsonReporter;
use SwooleProfiler\Reporters\HtmlReporter;

// Define User model
class User extends AsyncModel
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'active'];
}

// Configuration
$host = '0.0.0.0';
$port = 9501;

$poolConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int)(getenv('DB_PORT') ?: 5432),
    'database' => getenv('DB_NAME') ?: 'test_db',
    'username' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'password',
];

echo "Starting HTTP server with profiler on {$host}:{$port}\n";
echo "Available endpoints:\n";
echo "  GET  /users          - List all users\n";
echo "  GET  /users/{id}     - Get user by ID\n";
echo "  POST /users          - Create new user\n";
echo "  GET  /profiler       - View profiling report (HTML)\n";
echo "  GET  /profiler/json  - View profiling report (JSON)\n";
echo "  GET  /profiler/clear - Clear profiling data\n";
echo "\n";

// Create server
$server = new Server($host, $port);

$server->set([
    'worker_num' => 4,
    'enable_coroutine' => true,
]);

// Initialize on worker start
$server->on('WorkerStart', function (Server $server, int $workerId) use ($poolConfig) {
    echo "Worker #{$workerId} started\n";

    // Create connection pool for this worker
    $basePool = new SwoolePostgresPool($poolConfig, 10);
    $pool = new ProfiledPool($basePool);

    $baseConnection = new SwoolePostgresConnection($basePool, 'pgsql');
    $connection = new ProfiledConnection($baseConnection);

    // Store in worker context
    $server->connection = $connection;
    $server->pool = $pool;

    // Configure profiler
    $profiler = Profiler::getInstance();
    $profiler->enable();
    $profiler->setSlowQueryThreshold(100.0);
});

// Handle requests
$server->on('Request', function (Request $request, Response $response) use ($server) {
    $profiler = Profiler::getInstance();
    $connection = $server->connection;

    // Start profiling this request
    $profiler->startRequest($request->server['request_uri'], $request->server['request_method']);

    try {
        // Route the request
        $path = $request->server['path_info'] ?? $request->server['request_uri'];
        $method = $request->server['request_method'];

        // Set CORS headers
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Content-Type', 'application/json');

        switch (true) {
            // GET /users - List all users
            case $method === 'GET' && $path === '/users':
                $users = $connection->select('SELECT * FROM users ORDER BY id LIMIT 100');
                $response->status(200);
                $response->end(json_encode(['data' => $users]));
                break;

            // GET /users/{id} - Get user by ID
            case $method === 'GET' && preg_match('#^/users/(\d+)$#', $path, $matches):
                $id = (int)$matches[1];
                $user = $connection->selectOne('SELECT * FROM users WHERE id = ?', [$id]);

                if ($user) {
                    $response->status(200);
                    $response->end(json_encode(['data' => $user]));
                } else {
                    $response->status(404);
                    $response->end(json_encode(['error' => 'User not found']));
                }
                break;

            // POST /users - Create new user
            case $method === 'POST' && $path === '/users':
                $body = json_decode($request->rawContent(), true);

                if (!isset($body['name']) || !isset($body['email'])) {
                    $response->status(400);
                    $response->end(json_encode(['error' => 'Name and email are required']));
                    break;
                }

                $connection->insert(
                    'INSERT INTO users (name, email, active) VALUES (?, ?, ?)',
                    [$body['name'], $body['email'], $body['active'] ?? true]
                );

                $response->status(201);
                $response->end(json_encode(['message' => 'User created successfully']));
                break;

            // GET /profiler - HTML report
            case $method === 'GET' && $path === '/profiler':
                $reporter = new HtmlReporter($profiler);
                $response->header('Content-Type', 'text/html');
                $response->status(200);
                $response->end($reporter->report());
                return;

            // GET /profiler/json - JSON report
            case $method === 'GET' && $path === '/profiler/json':
                $reporter = new JsonReporter($profiler);
                $response->status(200);
                $response->end($reporter->report());
                return;

            // GET /profiler/clear - Clear profiling data
            case $method === 'GET' && $path === '/profiler/clear':
                $profiler->clear();
                $response->status(200);
                $response->end(json_encode(['message' => 'Profiling data cleared']));
                return;

            // 404 Not Found
            default:
                $response->status(404);
                $response->end(json_encode(['error' => 'Not found']));
                break;
        }

    } catch (\Throwable $e) {
        $response->status(500);
        $response->end(json_encode([
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
        ]));
    } finally {
        // End profiling and add headers
        $requestProfile = $profiler->endRequest();

        if ($requestProfile) {
            $response->header('X-Profiler-Query-Count', (string)$requestProfile->getQueryCount());
            $response->header('X-Profiler-Query-Time', sprintf('%.2f', $requestProfile->getTotalQueryTime()));
            $response->header('X-Profiler-Pool-Wait', sprintf('%.2f', $requestProfile->getTotalPoolWaitTime()));

            if ($duration = $requestProfile->getDuration()) {
                $response->header('X-Profiler-Duration', sprintf('%.2f', $duration));
            }
        }
    }
});

// Handle shutdown
$server->on('Shutdown', function () {
    echo "\nServer shutdown\n";
});

// Start server
$server->start();
