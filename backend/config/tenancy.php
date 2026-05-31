<?php

declare(strict_types=1);
use App\Models\Tenant;
use App\Services\CpanelTenantDatabaseManager;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;
use Stancl\Tenancy\UUIDGenerator;

return [

    'tenant_model' => Tenant::class,

    'id_generator' => UUIDGenerator::class,

    // Not using domain-based tenancy – identification is via session after login
    'central_domains' => [],

    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        // CacheTenancyBootstrapper requires a taggable driver (Redis/Memcached).
        // For file/array cache, Spatie permission cache is flushed in InitializeTenancyBySession.
        // \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null,

        // Tenant DB name: tenant_{id}  (e.g. tenant_550e8400-e29b-41d4-a716-446655440000)
        'prefix' => 'tenant_',
        'suffix' => '',

        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => env('CPANEL_USERNAME')
                ? CpanelTenantDatabaseManager::class       // shared hosting (cPanel)
                : MySQLDatabaseManager::class,  // local / VPS
            'mariadb' => env('CPANEL_USERNAME')
                ? CpanelTenantDatabaseManager::class
                : MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    // cPanel account username – used to prefix database names (e.g. ffhzczwexx_tenant_mood_shop).
    // Set CPANEL_USERNAME in server .env; leave empty for local development.
    'cpanel' => [
        'username' => env('CPANEL_USERNAME', ''),
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant_',
        'disks' => [],
        'root_override' => [],
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'queue' => [],

    'seeder_parameters' => [],

    // Tenant migrations live alongside central migrations.
    // Run: php artisan tenants:migrate
    'migration_parameters' => [
        '--path' => [database_path('migrations')],
        '--realpath' => true,
        '--force' => true,
    ],

    'routes' => false,

    'unique_identifier_generators' => [],

    // The tenant whose admin can manage other tenants via /admin/tenants.
    // Set MASTER_TENANT_ID in .env after creating your first tenant.
    'master_tenant' => env('MASTER_TENANT_ID'),
];
