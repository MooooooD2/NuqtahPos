<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * Tenant database manager for cPanel shared hosting.
 *
 * Creation strategy (tried in order):
 *   1. Shell: `uapi Mysql create_database` — works when PHP runs as the cPanel user (PHP-FPM).
 *      No credentials needed; available via SSH / exec on most cPanel hosts.
 *   2. HTTP UAPI with API token  (CPANEL_TOKEN in .env)
 *   3. HTTP UAPI with cPanel password (CPANEL_PASSWORD in .env)
 *
 * Required .env:
 *   CPANEL_USERNAME  – cPanel account name   (e.g. ffhzczwexx)
 *   CPANEL_HOST      – server hostname        (e.g. server50.shared.spaceship.host)
 *
 * Optional:
 *   CPANEL_TOKEN     – API token  (if shell exec is disabled)
 *   CPANEL_PASSWORD  – cPanel password (fallback when no token)
 */
class CpanelTenantDatabaseManager implements TenantDatabaseManager
{
    protected ?string $connection = null;

    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /** Internal name → cPanel-prefixed MySQL name (tenant_x → ffhzczwexx_tenant_x) */
    public function resolveDbName(string $name): string
    {
        $user = config('tenancy.cpanel.username');

        return $user ? "{$user}_{$name}" : $name;
    }

    // ── Creation strategies ───────────────────────────────────────────────────

    /** Strategy 1: shell `uapi` — needs no credentials, works when PHP runs as cPanel user. */
    protected function createViaShell(string $dbName, string $appUser): bool
    {
        if (! \function_exists('shell_exec')) {
            return false;
        }

        $db = escapeshellarg($dbName);
        $usr = escapeshellarg($appUser);

        $out = shell_exec("uapi Mysql create_database name={$db} 2>&1");

        if (! $out || str_contains($out, 'status: 0')) {
            return false;
        }

        shell_exec("uapi Mysql set_privileges_on_database user={$usr} database={$db} privileges='ALL PRIVILEGES' 2>&1");

        return $this->databaseExists(str_replace(config('tenancy.cpanel.username') . '_', '', $dbName));
    }

    /** Strategy 2 & 3: HTTP cPanel UAPI with token or password. */
    protected function createViaHttp(string $dbName, string $appUser): bool
    {
        $user = config('tenancy.cpanel.username');
        $token = config('tenancy.cpanel.token');
        $password = config('tenancy.cpanel.password');
        $host = config('tenancy.cpanel.host');
        $port = config('tenancy.cpanel.port', 2083);

        if (! $host || ! $token && ! $password) {
            return false;
        }

        $http = Http::withoutVerifying();
        $http = $token
            ? $http->withHeaders(['Authorization' => "cpanel {$user}:{$token}"])
            : $http->withBasicAuth($user, $password);

        $base = "https://{$host}:{$port}/execute/Mysql";

        $result = $http->get("{$base}/create_database", ['name' => $dbName])->json() ?? [];

        if (($result['status'] ?? 0) !== 1) {
            return false;
        }

        $http->get("{$base}/set_privileges_on_database", [
            'user' => $appUser,
            'database' => $dbName,
            'privileges' => 'ALL PRIVILEGES',
        ]);

        return true;
    }

    // ── TenantDatabaseManager interface ──────────────────────────────────────

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $dbName = $this->resolveDbName($tenant->database()->getName());
        $appUser = config('database.connections.' . ($this->connection ?? 'mysql') . '.username');

        if ($this->createViaShell($dbName, $appUser)) {
            return true;
        }

        if ($this->createViaHttp($dbName, $appUser)) {
            return true;
        }

        throw new RuntimeException(
            "Cannot create database [{$dbName}] automatically. " .
            'Add CPANEL_TOKEN or CPANEL_PASSWORD to .env, ' .
            "or create the database manually in cPanel and grant ALL PRIVILEGES to [{$appUser}].",
        );
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $dbName = $this->resolveDbName($tenant->database()->getName());

        if (\function_exists('shell_exec')) {
            shell_exec('uapi Mysql delete_database name=' . escapeshellarg($dbName) . ' 2>&1');

            return true;
        }

        $result = $this->cpanelHttpRequest('Mysql', 'delete_database', ['name' => $dbName]);

        return ($result['status'] ?? 0) === 1;
    }

    public function databaseExists(string $name): bool
    {
        $dbName = $this->resolveDbName($name);
        $rows = DB::connection($this->connection ?? 'mysql')
            ->select(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$dbName],
            );

        return \count($rows) > 0;
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['database'] = $this->resolveDbName($databaseName);

        return $baseConfig;
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    protected function cpanelHttpRequest(string $module, string $function, array $params = []): array
    {
        $user = config('tenancy.cpanel.username');
        $token = config('tenancy.cpanel.token');
        $password = config('tenancy.cpanel.password');
        $host = config('tenancy.cpanel.host');
        $port = config('tenancy.cpanel.port', 2083);

        $http = Http::withoutVerifying();
        $http = $token
            ? $http->withHeaders(['Authorization' => "cpanel {$user}:{$token}"])
            : $http->withBasicAuth($user, $password);

        return $http->get("https://{$host}:{$port}/execute/{$module}/{$function}", $params)->json() ?? [];
    }
}
