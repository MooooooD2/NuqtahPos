<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Tenant database manager for shared hosting environments.
 *
 * Shared hosting MySQL users typically lack CREATE DATABASE privilege.
 * This manager uses a second, privileged MySQL connection (DB_ADMIN_*)
 * only for CREATE / DROP DATABASE, while the regular app connection is
 * used for everything else.
 *
 * On cPanel hosts, database names must be prefixed with the cPanel account
 * name (e.g. ffhzczwexx_). Set CPANEL_USERNAME in .env to enable this.
 * UUID hyphens are stripped because cPanel disallows them in DB names.
 *
 * Required .env variables:
 *   DB_ADMIN_HOST       – MySQL host for the privileged user (usually same as DB_HOST)
 *   DB_ADMIN_USERNAME   – MySQL user with CREATE / DROP DATABASE privilege
 *   DB_ADMIN_PASSWORD   – Password for the privileged user
 *   CPANEL_USERNAME     – cPanel account name, used as database name prefix (optional)
 */
class PrivilegedTenantDatabaseManager implements TenantDatabaseManager
{
    protected ?string $connection = null;

    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Convert tenancy's internal DB name to a hosting-compatible MySQL name.
     *
     * Without cPanel: tenant_550e8400e29b41d4a716446655440000
     * With cPanel:    ffhzczwexx_tenant_550e8400e29b41d4a716446655440000
     */
    public function resolveDbName(string $name): string
    {
        $sanitized = str_replace('-', '', $name); // strip UUID hyphens
        $cpanelUser = config('tenancy.cpanel_username');

        return $cpanelUser ? "{$cpanelUser}_{$sanitized}" : $sanitized;
    }

    /** Run a statement on the privileged admin connection. */
    protected function adminStatement(string $sql): bool
    {
        return DB::connection('tenant_manager')->statement($sql);
    }

    /** Validate a MySQL identifier contains only safe characters. */
    protected function assertSafeIdentifier(string $value, string $label): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new InvalidArgumentException("Unsafe MySQL identifier for {$label}: {$value}");
        }
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $dbName = $this->resolveDbName($tenant->database()->getName());
        $charset = DB::connection($this->connection ?? 'mysql')->getConfig('charset');
        $collation = DB::connection($this->connection ?? 'mysql')->getConfig('collation');

        $this->assertSafeIdentifier($dbName, 'database name');
        $this->assertSafeIdentifier($charset, 'charset');
        $this->assertSafeIdentifier($collation, 'collation');

        return $this->adminStatement(
            "CREATE DATABASE `{$dbName}` CHARACTER SET `{$charset}` COLLATE `{$collation}`",
        );
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $dbName = $this->resolveDbName($tenant->database()->getName());
        $this->assertSafeIdentifier($dbName, 'database name');

        return $this->adminStatement("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    public function databaseExists(string $name): bool
    {
        $dbName = $this->resolveDbName($name);
        $rows = DB::connection($this->connection ?? 'mysql')
            ->select(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$dbName],
            );

        return count($rows) > 0;
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['database'] = $this->resolveDbName($databaseName);

        return $baseConfig;
    }
}
