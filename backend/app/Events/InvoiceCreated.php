<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an invoice is successfully persisted. Plugins can listen for
 * this to hook into the sale lifecycle without touching InvoiceService.
 */
class InvoiceCreated
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
