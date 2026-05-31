<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'direction', 'to_number', 'from_number', 'message_type',
        'template_name', 'message_body', 'related_type', 'related_id',
        'wa_message_id', 'status', 'error_message',
        'sent_at', 'delivered_at', 'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];
}
