<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $return_number
 * @property int $invoice_id
 * @property string|null $invoice_number
 * @property string|null $customer_name
 * @property float $total_amount
 * @property float $refund_amount
 * @property string|null $reason
 * @property string $status
 * @property string $refund_method
 * @property Carbon $return_date
 * @property int|null $processed_by
 * @property string|null $processed_by_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SalesReturn extends Model
{
    protected $fillable = [
        'return_number', 'invoice_id', 'invoice_number', 'customer_name',
        'total_amount', 'reason', 'status', 'return_date',
        'refund_method', 'refund_amount',
        'processed_by', 'processed_by_name',
    ];

    protected $hidden = ['processed_by'];

    protected $casts = [
        'return_date' => 'date',
        'total_amount' => 'float',
        'refund_amount' => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
