<?php

/**
 * Laravel Integration Example
 *
 * This file demonstrates how to use the profiler in a Laravel application.
 * Note: This is a documentation example, not a runnable script.
 */

// ============================================================================
// Step 1: Install the package
// ============================================================================
// composer require swoole-eloquent/profiler

// ============================================================================
// Step 2: Publish configuration (optional)
// ============================================================================
// php artisan vendor:publish --tag=profiler-config

// ============================================================================
// Step 3: Configure in config/profiler.php or .env
// ============================================================================
/*
PROFILER_ENABLED=true
PROFILER_SLOW_QUERY_THRESHOLD=100.0
PROFILER_AUTO_PROFILE_REQUESTS=true
PROFILER_ADD_HEADERS=true
*/

// ============================================================================
// Step 4: The service provider is auto-registered via package discovery
// ============================================================================

// ============================================================================
// Step 5: Use the profiler in your code
// ============================================================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SwooleProfiler\Laravel\Facades\Profiler;
use SwooleProfiler\Reporters\JsonReporter;

class UserController extends Controller
{
    /**
     * Example 1: Automatic profiling via middleware
     */
    public function index()
    {
        // Queries are automatically profiled
        $users = User::async()->where('active', true)->get();

        // Get current request profile
        $profile = Profiler::getCurrentRequest();

        return response()->json([
            'users' => $users,
            'query_count' => $profile?->getQueryCount(),
        ]);
    }

    /**
     * Example 2: Manual profiling control
     */
    public function create(Request $request)
    {
        // Start profiling
        Profiler::startRequest('/users/create', 'POST');

        $user = new User();
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->asyncSave();

        // End profiling and get metrics
        $profile = Profiler::endRequest();

        return response()->json([
            'user' => $user,
            'profiler' => [
                'queries' => $profile->getQueryCount(),
                'duration' => $profile->getDuration(),
            ],
        ]);
    }

    /**
     * Example 3: Get profiling report
     */
    public function profilerReport()
    {
        $reporter = new JsonReporter(Profiler::getInstance());

        return response()->json($reporter->getData());
    }

    /**
     * Example 4: Get slow queries
     */
    public function slowQueries()
    {
        $slowQueries = Profiler::getSlowQueries(50.0); // Queries > 50ms

        $formatted = array_map(function ($query) {
            return [
                'sql' => $query->sql,
                'duration' => $query->duration,
                'bindings' => $query->bindings,
            ];
        }, $slowQueries);

        return response()->json(['slow_queries' => $formatted]);
    }

    /**
     * Example 5: Transaction profiling
     */
    public function transferFunds(Request $request)
    {
        DB::beginTransaction();

        try {
            // These queries are automatically tracked
            Account::async()
                ->where('id', $request->from_account)
                ->asyncUpdate(['balance' => DB::raw('balance - ' . $request->amount)]);

            Account::async()
                ->where('id', $request->to_account)
                ->asyncUpdate(['balance' => DB::raw('balance + ' . $request->amount)]);

            DB::commit();

            // Get transaction metrics
            $metrics = Profiler::getMetrics();

            return response()->json([
                'success' => true,
                'transaction_metrics' => $metrics['transactions'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Example 6: Clear profiling data
     */
    public function clearProfiler()
    {
        Profiler::clear();

        return response()->json(['message' => 'Profiler data cleared']);
    }
}

// ============================================================================
// Step 6: Add profiler middleware to specific routes
// ============================================================================

// In routes/api.php
/*
use SwooleProfiler\Laravel\ProfilerMiddleware;

Route::middleware(['profiler'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'create']);
});

// Or apply to all routes
Route::middleware('web')->group(function () {
    // All routes here will be profiled
});
*/

// ============================================================================
// Step 7: View profiling data in responses (when enabled)
// ============================================================================
/*
curl http://localhost/api/users?_profiler=1

Response:
{
    "users": [...],
    "_profiler": {
        "coroutine_id": 123,
        "query_count": 5,
        "total_query_time": 45.23,
        "total_pool_wait_time": 2.15,
        "slowest_query": {
            "sql": "SELECT * FROM users WHERE active = ?",
            "duration": 15.67
        }
    }
}
*/

// ============================================================================
// Step 8: Custom profiler configuration
// ============================================================================

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SwooleProfiler\Profiler;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $profiler = app(Profiler::class);

        // Customize slow query threshold
        $profiler->setSlowQueryThreshold(50.0);

        // Conditionally enable/disable
        if (app()->environment('production')) {
            $profiler->disable();
        }
    }
}
