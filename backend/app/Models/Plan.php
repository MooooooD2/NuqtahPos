<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $connection = 'mysql';  // central DB

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'monthly_price', 'annual_price',
        'trial_days', 'max_users', 'max_products',
        'features', 'feature_flags', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'float',
        'annual_price' => 'float',
        'trial_days' => 'integer',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'features' => 'array',
        'feature_flags' => 'array',   // structured feature keys list
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Check whether this plan includes a specific feature key. */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->feature_flags ?? [], strict: true);
    }

    public function annualMonthlyRate(): float
    {
        return $this->annual_price ? round($this->annual_price / 12, 2) : $this->monthly_price;
    }

    public function annualSavings(): float
    {
        if (! $this->annual_price) {
            return 0;
        }

        return round(($this->monthly_price * 12) - $this->annual_price, 2);
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class, 'plan', 'id');
    }
}
