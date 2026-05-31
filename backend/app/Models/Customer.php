<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $code
 * @property int|null $customer_group_id
 * @property string|null $type
 * @property string $price_level
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $national_id
 * @property string|null $tax_number
 * @property string|null $commercial_register
 * @property string|null $governate
 * @property string|null $city
 * @property string|null $address
 * @property string $credit_limit
 * @property string $balance
 * @property int $loyalty_points
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read float       $available_credit
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'customer_group_id', 'type', 'price_level', 'name', 'phone', 'email',
        'national_id', 'tax_number', 'commercial_register',
        'governate', 'city', 'address',
        'credit_limit', 'loyalty_points', 'notes', 'is_active',
    ];

    // balance is never in fillable — only CustomerAccountService touches it
    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccount::class);
    }

    public function getAvailableCreditAttribute(): float
    {
        return (float) ($this->credit_limit - $this->balance);
    }
}
