<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

return [

    'backup' => [
        'name' => env('APP_NAME', 'POS-System'),

        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('public/build'),
                    storage_path('logs'),
                    storage_path('framework'),
                    storage_path('app/backup-temp'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => null,
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => GzipCompressor::class,
        'database_dump_file_timestamp_format' => 'Y-m-d-H-i-s',
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => 'backup-',
            'disks' => [
                env('BACKUP_DISK', 'local'),
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',
        'tries' => 3,
        'retry_delay' => 10,
    ],

    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFY_EMAIL', env('MAIL_FROM_ADDRESS', 'admin@example.com')),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'backup@example.com'),
                'name' => env('MAIL_FROM_NAME', 'POS Backup'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'POS-System'),
            'disks' => [env('BACKUP_DISK', 'local')],
            'health_checks' => [
                MaximumAgeInDays::class => 2,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
