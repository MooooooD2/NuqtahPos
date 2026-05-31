<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => ['channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'), 'trace' => env('LOG_DEPRECATIONS_TRACE', false)],
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => explode(',', env('LOG_STACK', 'single')), 'ignore_exceptions' => false],
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'warning'), 'replace_placeholders' => true],
        'daily' => ['driver' => 'daily', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'warning'), 'days' => env('LOG_DAILY_DAYS', 14), 'replace_placeholders' => true],

        // #38-43 Audit Trail منفصل لكل العمليات الحساسة
        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'days' => 90,   // احتفظ بـ 90 يوم
            'replace_placeholders' => true,
        ],

        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
        'stderr' => ['driver' => 'monolog', 'level' => env('LOG_LEVEL', 'warning'), 'handler' => StreamHandler::class, 'formatter' => env('LOG_STDERR_FORMATTER'), 'with' => ['stream' => 'php://stderr'], 'processors' => [PsrLogMessageProcessor::class]],
    ],
];
