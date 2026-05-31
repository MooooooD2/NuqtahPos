<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    // Insert-only — no updates ever
    public $timestamps = false;

    protected $fillable = [
        'action', 'model', 'record_id',
        'user_id', 'username', 'ip_address', 'user_agent',
        'changes', 'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Guard all mutation operations — audit logs are immutable
    public static function boot(): void
    {
        parent::boot();

        static::updating(fn () => throw new LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit logs are immutable.'));
    }
}
