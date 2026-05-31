<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    // #7 is_active و role في fillable لكن id و remember_token ليسا
    protected $fillable = [
        'username', 'email', 'password', 'full_name', 'role', 'is_active', 'language',
        'branch_id',
        'google2fa_secret', 'google2fa_enabled', 'google2fa_recovery_codes',
    ];

    // #35 إخفاء البيانات الحساسة
    protected $hidden = ['password', 'remember_token', 'deleted_at', 'google2fa_secret', 'google2fa_recovery_codes'];

    protected $casts = [
        'is_active' => 'boolean',
        'google2fa_enabled' => 'boolean',
        'google2fa_recovery_codes' => 'array',
    ];

    protected $connection = 'tenant';

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'cashier_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Query scopes ──────────────────────────────────────────────────────────

    /**
     * Scope: only active employees (used by PayrollService and HR reports).
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
