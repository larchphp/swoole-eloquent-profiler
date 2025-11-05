<?php

declare(strict_types=1);

namespace SwooleProfiler\Laravel;

use Closure;
use Illuminate\Http\Request;
use SwooleProfiler\Profiler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for profiling HTTP requests in Laravel applications
 */
class ProfilerMiddleware
{
    public function __construct(
        private readonly Profiler $profiler
    ) {
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Start profiling the request
        $this->profiler->startRequest(
            path: $request->path(),
            method: $request->method(),
        );

        // Process the request
        $response = $next($request);

        // End profiling
        $requestProfile = $this->profiler->endRequest();

        // Add profiling headers if configured
        if (config('profiler.add_headers', false) && $requestProfile) {
            $response->headers->set('X-Profiler-Query-Count', (string)$requestProfile->getQueryCount());
            $response->headers->set('X-Profiler-Query-Time', sprintf('%.2f', $requestProfile->getTotalQueryTime()));
            $response->headers->set('X-Profiler-Pool-Wait-Time', sprintf('%.2f', $requestProfile->getTotalPoolWaitTime()));

            if ($duration = $requestProfile->getDuration()) {
                $response->headers->set('X-Profiler-Duration', sprintf('%.2f', $duration));
            }
        }

        // Add profiling data to response if configured and it's a JSON response
        if (config('profiler.add_to_response', false) && $this->shouldAddToResponse($request, $response)) {
            $this->addProfilingToResponse($response, $requestProfile);
        }

        return $response;
    }

    /**
     * Check if profiling data should be added to response
     */
    private function shouldAddToResponse(Request $request, Response $response): bool
    {
        // Only add to JSON responses
        if (!str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            return false;
        }

        // Check if explicitly requested via query parameter
        if ($request->query('_profiler')) {
            return true;
        }

        // Check if debug mode is enabled
        return config('app.debug', false);
    }

    /**
     * Add profiling data to JSON response
     */
    private function addProfilingToResponse(Response $response, $requestProfile): void
    {
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return;
        }

        $data['_profiler'] = $requestProfile?->toArray();

        $response->setContent(json_encode($data));
    }
}
