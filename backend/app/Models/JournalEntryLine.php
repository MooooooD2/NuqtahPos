<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    protected $fillable = ['entry_id', 'account_id', 'debit', 'credit', 'description'];

    protected $casts = ['debit' => 'decimal:4', 'credit' => 'decimal:4'];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public static function boot(): void
    {
        parent::boot();

        // Lines of a posted entry cannot be modified or deleted
        static::updating(function (JournalEntryLine $line) {
            if ($line->entry?->is_posted) {
                throw new DomainException(__('pos.journal_entry_posted_immutable'));
            }
        });

        static::deleting(function (JournalEntryLine $line) {
            if ($line->entry?->is_posted) {
                throw new DomainException(__('pos.journal_entry_posted_immutable'));
            }
        });
    }
}
