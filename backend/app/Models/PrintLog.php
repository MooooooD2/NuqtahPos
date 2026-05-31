<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'printer_id', 'document_type', 'document_id',
        'document_number', 'copies', 'printed_by',
        'print_method', 'success', 'notes',
    ];

    protected $casts = [
        'copies' => 'integer',
        'success' => 'boolean',
    ];

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
