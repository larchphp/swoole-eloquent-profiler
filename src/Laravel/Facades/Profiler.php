<?php

declare(strict_types=1);

namespace SwooleProfiler\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Profiler facade for Laravel
 *
 * @method static void enable()
 * @method static void disable()
 * @method static bool isEnabled()
 * @method static void setSlowQueryThreshold(float $thresholdMs)
 * @method static float getSlowQueryThreshold()
 * @method static void startRequest(?string $path = null, ?string $method = null)
 * @method static \SwooleProfiler\Data\RequestProfile|null endRequest()
 * @method static void recordQuery(string $sql, array $bindings, float $duration, float $poolWaitTime = 0.0, bool $success = true, ?string $error = null, ?int $affectedRows = null, ?string $connectionName = null)
 * @method static void startTransaction(int $level = 1, ?string $connectionName = null)
 * @method static void endTransaction(int $level = 1, string $status = 'committed')
 * @method static void recordPoolMetrics(int $size, int $active, int $idle, int $waiting, ?string $connectionName = null)
 * @method static \SwooleProfiler\Data\RequestProfile|null getCurrentRequest()
 * @method static array getQueries()
 * @method static array getSlowQueries(?float $threshold = null)
 * @method static array getMetrics()
 * @method static string toJson(int $flags = JSON_PRETTY_PRINT)
 * @method static void clear()
 *
 * @see \SwooleProfiler\Profiler
 */
class Profiler extends Facade
{
    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return \SwooleProfiler\Profiler::class;
    }
}
