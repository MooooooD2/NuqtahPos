<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    /**
     * Called after the base TestCase bootstraps (and after RefreshDatabase
     * has already started its wrapping transaction on the default connection).
     *
     * Problem: User::$connection = 'tenant' (separate MySQL connection object).
     * RefreshDatabase only wraps the *default* connection in a transaction.
     * When a test inserts via `DB::table(...)` (default/mysql) and then does
     * `User::factory()->create()` (tenant), the two connections hold competing
     * InnoDB locks on the same rows → lock-wait timeouts; and the default
     * connection's transaction snapshot can't see the tenant's uncommitted rows
     * → `exists:tenant.users,id` validation failures.
     *
     * Fix: after RefreshDatabase opens its transaction on the mysql connection,
     * make the 'tenant' connection re-use the exact same PDO handle.  Both
     * connections now share one TCP session → one lock context → no deadlocks,
     * and uncommitted inserts are visible to either "connection".
     */
    protected function setUp(): void
    {
        parent::setUp(); // ← RefreshDatabase transaction starts here

        $this->shareTenantPdoWithDefault();
    }

    private function shareTenantPdoWithDefault(): void
    {
        try {
            $default = config('database.default');

            // Resolve actual drivers (not just config — a test may have extended
            // the 'tenant' resolver to return a different connection type).
            $defaultDriver = DB::connection($default)->getDriverName();
            $tenantDriver = DB::connection('tenant')->getDriverName();

            // Only merge when both connections genuinely use the same MySQL/MariaDB
            // driver.  If a test overrides 'tenant' to SQLite, leave it alone.
            if ($defaultDriver !== $tenantDriver
                || ! in_array($defaultDriver, ['mysql', 'mariadb'], true)) {
                return;
            }

            $pdo = DB::connection($default)->getPdo();

            // Both connections now share the same underlying PDO session.
            DB::connection('tenant')
                ->setPdo($pdo)
                ->setReadPdo($pdo);
        } catch (Throwable) {
            // If the 'tenant' connection is misconfigured (CI / other env), skip.
        }
    }
}
