<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $session_number
 * @property int $cashier_id
 * @property string $cashier_name
 * @property float $opening_amount
 * @property float $expected_cash
 * @property float $actual_cash
 * @property float $difference
 * @property float $total_sales
 * @property float $total_returns
 * @property float $total_card
 * @property float $total_transfer
 * @property int $invoices_count
 * @property string $status
 * @property string|null $notes
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CashRegisterSession extends Model
{
    protected $fillable = [
        'session_number', 'cashier_id', 'cashier_name',
        'opening_amount', 'expected_cash', 'actual_cash', 'difference',
        'total_sales', 'total_returns', 'total_card', 'total_transfer',
        'invoices_count', 'status', 'notes', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'cashier_id' => 'integer',
        'invoices_count' => 'integer',
        'opening_amount' => 'float',
        'expected_cash' => 'float',
        'actual_cash' => 'float',
        'difference' => 'float',
        'total_sales' => 'float',
        'total_returns' => 'float',
        'total_card' => 'float',
        'total_transfer' => 'float',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
