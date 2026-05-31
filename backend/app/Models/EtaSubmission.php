<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EtaSubmission extends Model
{
    protected $fillable = [
        'submission_id', 'document_count',
        'accepted_documents', 'rejected_documents',
        'raw_request', 'raw_response', 'submitted_at',
    ];

    protected $casts = [
        'accepted_documents' => 'array',
        'rejected_documents' => 'array',
        'raw_request' => 'array',
        'raw_response' => 'array',
        'submitted_at' => 'datetime',
    ];
}
