<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'printer_id', 'document_type', 'document_id',
        'document_number', 'status', 'attempts',
        'max_attempts', 'raw_data', 'error_message',
        'processed_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->whereColumn('attempts', '<', 'max_attempts');
    }

    // ── State Transitions ──────────────────────────────────────────────────────

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->increment('attempts');
        $this->update([
            'status' => $this->attempts >= $this->max_attempts ? 'failed' : 'pending',
            'error_message' => $error,
        ]);
    }

    public function retry(): bool
    {
        if ($this->attempts >= $this->max_attempts) {
            return false;
        }
        $this->update(['status' => 'pending', 'error_message' => null]);

        return true;
    }
}
