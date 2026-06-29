<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class License extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id', 'key_prefix', 'license_key', 'key_encrypted', 'device_id', 'device_name',
        'status', 'activated_at', 'expires_at', 'last_validated_at',
    ];

    protected $hidden = ['license_key', 'key_encrypted'];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Generate a fresh plain-text license key in the form XXXX-XXXX-XXXX-XXXX.
     */
    public static function generateKey(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = Str::upper(Str::random(4));
        }

        return implode('-', $segments);
    }

    public static function hashKey(string $plainKey): string
    {
        return Hash::make($plainKey);
    }

    public static function keyPrefix(string $plainKey): string
    {
        return substr(str_replace('-', '', $plainKey), 0, 8);
    }
}
