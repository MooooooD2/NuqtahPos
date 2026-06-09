<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // FIX-2: التحقق من وجود كلمات المرور قبل الـ seed
        $this->validatePasswords();

        $adminRole = Role::firstOrCreate(['name' => 'admin',     'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier',   'guard_name' => 'web']);
        $warehouseRole = Role::firstOrCreate(['name' => 'warehouse',  'guard_name' => 'web']);

        $admin = User::firstOrCreate(['username' => 'admin'], [
            'password' => Hash::make(config('app.seed_admin_password')),
            'full_name' => 'المدير العام',
            'role' => 'admin',
            'is_active' => true,
            'language' => 'ar',
        ]);
        $admin->syncRoles([$adminRole]);

        $cashier = User::firstOrCreate(['username' => 'cashier'], [
            'password' => Hash::make(config('app.seed_cashier_password')),
            'full_name' => 'أمين الصندوق',
            'role' => 'cashier',
            'is_active' => true,
            'language' => 'ar',
        ]);
        $cashier->syncRoles([$cashierRole]);

        $warehouse = User::firstOrCreate(['username' => 'warehouse'], [
            'password' => Hash::make(config('app.seed_warehouse_password')),
            'full_name' => 'مسؤول المخزن',
            'role' => 'warehouse',
            'is_active' => true,
            'language' => 'ar',
        ]);
        $warehouse->syncRoles([$warehouseRole]);

        $this->command->info('✅ Users seeded successfully.');
    }

    /**
     * FIX-2: التحقق من أن كلمات المرور غير فارغة قبل الـ seed
     * يمنع إنشاء حسابات بكلمات مرور فارغة عن طريق الخطأ
     */
    private function validatePasswords(?array $passwords = null): void
    {
        $required = $passwords ?? [
            'ADMIN_PASSWORD' => config('app.seed_admin_password'),
            'CASHIER_PASSWORD' => config('app.seed_cashier_password'),
            'WAREHOUSE_PASSWORD' => config('app.seed_warehouse_password'),
        ];

        $missing = [];
        foreach ($required as $key => $value) {
            if (empty($value)) {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            if ($this->command) {
                $this->command->error('❌ الـ Seeder توقف — كلمات المرور التالية فارغة في .env:');
                foreach ($missing as $key) {
                    $this->command->error("   - {$key}");
                }
                $this->command->error('   أضف كلمات مرور قوية في ملف .env ثم أعد تشغيل الـ seed.');
            }

            throw new RuntimeException('Seed aborted: missing required passwords in .env');
        }

        // التحقق من أن كلمات المرور تستوفي الحد الأدنى من المتطلبات
        foreach ($required as $key => $value) {
            if (\strlen($value) < 8) {
                throw new RuntimeException(
                    "Seed aborted: {$key} must be at least 8 characters long.",
                );
            }
        }
    }
}
