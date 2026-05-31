<?php

/**
 * Phase 0 — Error Monitoring: Sentry Configuration
 * Install: composer require sentry/sentry-laravel
 */
return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN', '')),

    // Capture 100% of errors in production, 10% of performance traces
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Profile 10% of sampled transactions
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'send_default_pii' => false,

    'environment' => env('APP_ENV', 'production'),

    'release' => env('SENTRY_RELEASE', null),

    // Breadcrumbs for better context
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => true,
        'sql_queries' => true,
        'sql_bindings' => env('APP_ENV') !== 'production', // no PII in prod
        'queue_info' => true,
        'command_info' => true,
    ],

    // Performance monitoring
    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_origin' => true,
        'views' => true,
        'http_client_requests' => true,
        'redis_commands' => env('APP_ENV') !== 'production',
        'missing_routes' => true,
        'livewire_components' => true,
    ],

    // Ignore common non-critical exceptions
    'ignore_exceptions' => [
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Auth\Access\AuthorizationException::class,
        Illuminate\Validation\ValidationException::class,
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        Illuminate\Session\TokenMismatchException::class,
    ],
];
