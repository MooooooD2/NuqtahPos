<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label_ar', 'label_en'];

    /**
     * Get a setting value by key - الحصول على قيمة إعداد
     */
    public static function get(string $key, $default = null)
    {
        $tenantKey = tenancy()->tenant?->getTenantKey() ?? 'central';
        $setting = Cache::remember("setting_{$tenantKey}_{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => (bool) $setting->value,
            'number' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    /**
     * Set a setting value - تعيين قيمة إعداد
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        $tenantKey = tenancy()->tenant?->getTenantKey() ?? 'central';
        Cache::forget("setting_{$tenantKey}_{$key}");
    }

    /**
     * Get all settings by group - الحصول على كل إعدادات مجموعة
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)->get()
            ->keyBy('key')
            ->map(fn ($s) => [
                'key' => $s->key,
                'value' => $s->value,
                'type' => $s->type,
                'label_ar' => $s->label_ar,
                'label_en' => $s->label_en,
            ])->toArray();
    }

    /**
     * Get all settings grouped - كل الإعدادات مجمعة
     */
    public static function getAllGrouped(): array
    {
        return static::all()
            ->groupBy('group')
            ->map(fn ($g) => $g->keyBy('key')->map(fn ($s) => [
                'value' => $s->value,
                'type' => $s->type,
                'label_ar' => $s->label_ar,
                'label_en' => $s->label_en,
            ]))
            ->toArray();
    }
}
