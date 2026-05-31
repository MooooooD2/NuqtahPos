<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSessionMovement extends Model
{
    protected $fillable = ['cash_session_id', 'type', 'amount', 'reason', 'user_id'];

    protected $casts = ['amount' => 'decimal:4'];

    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
