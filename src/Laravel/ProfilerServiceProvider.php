<?php

declare(strict_types=1);

namespace SwooleProfiler\Laravel;

use Illuminate\Support\ServiceProvider;
use SwooleProfiler\Profiler;

/**
 * Laravel service provider for the profiler
 */
class ProfilerServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/profiler.php',
            'profiler'
        );

        // Register profiler as singleton
        $this->app->singleton(Profiler::class, function ($app) {
            $profiler = Profiler::getInstance();

            // Configure from config file
            if (!config('profiler.enabled', true)) {
                $profiler->disable();
            }

            $profiler->setSlowQueryThreshold(
                config('profiler.slow_query_threshold', 100.0)
            );

            return $profiler;
        });

        // Register alias
        $this->app->alias(Profiler::class, 'profiler');
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/profiler.php' => config_path('profiler.php'),
            ], 'profiler-config');
        }

        // Register middleware
        if (config('profiler.auto_profile_requests', true)) {
            $this->registerMiddleware();
        }

        // Listen to database query events if enabled
        if (config('profiler.listen_query_events', false)) {
            $this->listenToQueryEvents();
        }
    }

    /**
     * Register middleware
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('profiler', ProfilerMiddleware::class);
        }

        // Auto-register middleware globally if configured
        if (config('profiler.auto_register_middleware', false)) {
            $router->pushMiddlewareToGroup('web', ProfilerMiddleware::class);
            $router->pushMiddlewareToGroup('api', ProfilerMiddleware::class);
        }
    }

    /**
     * Listen to Laravel database query events
     */
    protected function listenToQueryEvents(): void
    {
        $profiler = $this->app->make(Profiler::class);

        $this->app['db']->listen(function ($query) use ($profiler) {
            $profiler->recordQuery(
                sql: $query->sql,
                bindings: $query->bindings,
                duration: $query->time,
                poolWaitTime: 0.0,
                success: true,
                connectionName: $query->connectionName,
            );
        });
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [Profiler::class, 'profiler'];
    }
}
