<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SequenceService — Atomic document numbering
 * خدمة الترقيم التسلسلي الآمن — يمنع تكرار الأرقام في حال وجود طلبات متزامنة
 *
 * Uses MySQL LAST_INSERT_ID() trick for atomic increment.
 * Safe under concurrent requests — no race conditions.
 */
class SequenceService
{
    /**
     * Get the next number in a sequence.
     * الحصول على الرقم التالي في التسلسل بشكل آمن وذري
     *
     * FIX-5: تسجيل خطأ إذا فشل الـ sequence بدلاً من الفشل الصامت
     *
     * @param string $name e.g. 'invoice', 'purchase', 'return'
     * @param string|null $prefix Override prefix (optional, uses DB default)
     * @return string e.g. 'INV-20260425-000001'
     */
    public static function next(string $name, ?string $prefix = null): string
    {
        ['id' => $id, 'prefix' => $resolvedPrefix] = DB::transaction(function () use ($name, $prefix) {
            $row = DB::table('sequences')->where('name', $name)->lockForUpdate()->first();

            if (! $row) {
                Log::warning('sequence.not_found_creating', [
                    'name' => $name,
                    'timestamp' => now()->toIso8601String(),
                ]);
                DB::table('sequences')->insert([
                    'name' => $name,
                    'value' => 1,
                    'prefix' => $prefix ?? strtoupper($name),
                ]);

                return ['id' => 1, 'prefix' => $prefix ?? strtoupper($name)];
            }

            $newValue = $row->value + 1;
            DB::table('sequences')->where('name', $name)->update(['value' => $newValue]);

            return ['id' => $newValue, 'prefix' => $prefix ?? $row->prefix ?? strtoupper($name)];
        });

        return "{$resolvedPrefix}-" . now()->format('Ymd') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    }
}
