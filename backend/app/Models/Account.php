<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $account_code
 * @property string $account_name
 * @property string $account_type
 * @property int|null $parent_id
 * @property string|null $description
 * @property string $balance
 */
class Account extends Model
{
    // #7 balance محذوف من fillable — يُحسب تلقائياً من journal lines
    protected $fillable = ['account_code', 'account_name', 'account_type', 'parent_id', 'description'];

    protected $casts = ['balance' => 'decimal:4'];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
