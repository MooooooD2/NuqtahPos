<?php

namespace Tests\Feature;

use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * FIX-2: اختبار أن الـ Seeder يرفض كلمات المرور الفارغة
 */
class SeederValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seeder_throws_exception_when_admin_password_is_empty()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing required passwords/i');

        $seeder = new UserSeeder;
        $method = new ReflectionMethod($seeder, 'validatePasswords');
        $method->setAccessible(true);
        $method->invoke($seeder, [
            'ADMIN_PASSWORD' => '',
            'CASHIER_PASSWORD' => 'CashierPass1',
            'WAREHOUSE_PASSWORD' => 'WarehousePass1',
        ]);
    }
}
