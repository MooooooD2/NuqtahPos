<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — Multi-Currency
 */
class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate',
        'is_base', 'is_active', 'rate_updated_at',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:8',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'rate_updated_at' => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeBase(Builder $q): Builder
    {
        return $q->where('is_base', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert an amount FROM this currency TO base currency.
     */
    public function toBase(float $amount): float
    {
        return $this->is_base ? $amount : round($amount / (float) $this->exchange_rate, 4);
    }

    /**
     * Convert an amount FROM base currency TO this currency.
     */
    public function fromBase(float $amount): float
    {
        return $this->is_base ? $amount : round($amount * (float) $this->exchange_rate, 4);
    }

    /**
     * Format an amount with this currency's symbol.
     */
    public function format(float $amount): string
    {
        return $this->symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Get the current base currency.
     */
    public static function base(): self
    {
        return static::where('is_base', true)->firstOrFail();
    }
}
