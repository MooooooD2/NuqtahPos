<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $currencies = [
            // Base currency first
            [
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'symbol' => 'ج.م',
                'exchange_rate' => 1.000000,
                'is_base' => true,
                'is_active' => true,
                'rate_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate' => 0.020400,   // 1 EGP ≈ 0.0204 USD  (≈ 49 EGP per USD)
                'is_base' => false,
                'is_active' => true,
                'rate_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'exchange_rate' => 0.018800,   // ≈ 53 EGP per EUR
                'is_base' => false,
                'is_active' => true,
                'rate_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'SAR',
                'name' => 'Saudi Riyal',
                'symbol' => 'ر.س',
                'exchange_rate' => 0.076500,   // ≈ 13 EGP per SAR
                'is_base' => false,
                'is_active' => true,
                'rate_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'AED',
                'name' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'exchange_rate' => 0.074900,   // ≈ 13.4 EGP per AED
                'is_base' => false,
                'is_active' => true,
                'rate_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound',
                'symbol' => '£',
                'exchange_rate' => 0.016100,   // ≈ 62 EGP per GBP
                'is_base' => false,
                'is_active' => false,
                'rate_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Use upsert so running twice doesn't fail
        DB::table('currencies')->upsert(
            $currencies,
            ['code'],           // conflict column
            ['name', 'symbol', 'exchange_rate', 'is_base', 'is_active', 'rate_updated_at', 'updated_at'],
        );

        // Clear any stale cache
        \Illuminate\Support\Facades\Cache::forget('currencies');
        \Illuminate\Support\Facades\Cache::forget('currencies.active');
    }
}
