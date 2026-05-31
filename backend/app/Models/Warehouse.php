<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $branch_id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property string|null $keeper_name
 * @property bool $is_default
 * @property bool $is_active
 * @property bool $is_locked
 * @property Carbon|null $locked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Warehouse extends Model
{
    protected $fillable = ['branch_id', 'name', 'code', 'address', 'keeper_name', 'is_default', 'is_active', 'is_locked'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function stock()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function transfersFrom()
    {
        return $this->hasMany(WarehouseTransfer::class, 'from_warehouse_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(WarehouseTransfer::class, 'to_warehouse_id');
    }
}
