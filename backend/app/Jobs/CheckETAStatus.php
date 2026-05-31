<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\ETA\ETAClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckETAStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [120, 300, 600];

    public function __construct(public int $invoiceId) {}

    public function handle(ETAClient $client): void
    {
        $invoice = Invoice::findOrFail($this->invoiceId);

        if (in_array($invoice->eta_status, ['valid', 'cancelled'])) {
            return;
        }

        if (! $invoice->eta_uuid) {
            return;
        }

        $status = $client->getDocumentStatus($invoice->eta_uuid);
        $etaState = $status['status'] ?? null;

        if ($etaState) {
            $invoice->update([
                'eta_status' => strtolower($etaState),
                'eta_response' => json_encode($status),
            ]);
        }
    }
}
