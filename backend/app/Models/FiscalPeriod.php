<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property int|null $closing_entry_id
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 */
class FiscalPeriod extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'closing_entry_id', 'closed_at', 'closed_by'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function closingEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'closing_entry_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function entries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Find the period (if any) that contains the given date.
     */
    public static function forDate(string $date): ?self
    {
        return self::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}
