<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profiler Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the profiler globally. When disabled, no profiling
    | data will be collected.
    |
    */
    'enabled' => env('PROFILER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Slow Query Threshold
    |--------------------------------------------------------------------------
    |
    | Define the threshold in milliseconds for what constitutes a "slow" query.
    | Queries taking longer than this will be flagged in reports.
    |
    */
    'slow_query_threshold' => env('PROFILER_SLOW_QUERY_THRESHOLD', 100.0),

    /*
    |--------------------------------------------------------------------------
    | Auto Profile Requests
    |--------------------------------------------------------------------------
    |
    | Automatically profile all HTTP requests. When enabled, the profiler
    | middleware will be registered to track request metrics.
    |
    */
    'auto_profile_requests' => env('PROFILER_AUTO_PROFILE_REQUESTS', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Register Middleware
    |--------------------------------------------------------------------------
    |
    | Automatically register the profiler middleware globally for web and api
    | groups. If false, you need to manually add the middleware to your routes.
    |
    */
    'auto_register_middleware' => env('PROFILER_AUTO_REGISTER_MIDDLEWARE', false),

    /*
    |--------------------------------------------------------------------------
    | Listen to Query Events
    |--------------------------------------------------------------------------
    |
    | Listen to Laravel's database query events. This is an alternative way
    | to capture queries without using decorators. May have slight overhead.
    |
    */
    'listen_query_events' => env('PROFILER_LISTEN_QUERY_EVENTS', false),

    /*
    |--------------------------------------------------------------------------
    | Add Headers to Response
    |--------------------------------------------------------------------------
    |
    | Add profiling information to HTTP response headers. Useful for monitoring
    | and debugging without modifying the response body.
    |
    */
    'add_headers' => env('PROFILER_ADD_HEADERS', false),

    /*
    |--------------------------------------------------------------------------
    | Add Profiling Data to Response
    |--------------------------------------------------------------------------
    |
    | Add profiling data to JSON responses. Only works for JSON responses
    | and when debug mode is enabled or when ?_profiler=1 is in the query.
    |
    */
    'add_to_response' => env('PROFILER_ADD_TO_RESPONSE', false),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how profiling data is stored. Options include memory limits
    | and retention policies.
    |
    */
    'storage' => [
        'max_queries' => env('PROFILER_MAX_QUERIES', 1000),
        'max_requests' => env('PROFILER_MAX_REQUESTS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    |
    | Default output format for reports. Options: cli, json, html
    |
    */
    'output_format' => env('PROFILER_OUTPUT_FORMAT', 'cli'),
];
