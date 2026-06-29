<?php

namespace Plugins\ExampleAuditLogger;

use App\Events\InvoiceCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AuditLoggerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(InvoiceCreated::class, function (InvoiceCreated $event) {
            Log::channel('audit')->info('plugin.example_audit_logger.invoice_created', [
                'invoice_id' => $event->invoice->id,
                'final_total' => $event->invoice->final_total,
            ]);
        });
    }
}
