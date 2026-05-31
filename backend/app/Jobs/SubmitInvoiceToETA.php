<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\ETA\ETAClient;
use App\Services\ETA\InvoiceBuilder;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitInvoiceToETA implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(public int $invoiceId) {}

    public function handle(ETAClient $client, InvoiceBuilder $builder): void
    {
        if (! config('eta.enabled')) {
            return;
        }

        $invoice = Invoice::with('items.product', 'customer')->findOrFail($this->invoiceId);

        if ($invoice->eta_status === 'valid') {
            return;
        }

        $document = $builder->build($invoice);
        $response = $client->submitDocuments([$document]);

        if (! empty($response['acceptedDocuments'])) {
            $accepted = $response['acceptedDocuments'][0];

            $invoice->update([
                'eta_uuid' => $accepted['uuid'],
                'eta_long_id' => $accepted['longId'],
                'eta_submission_id' => $response['submissionId'],
                'eta_status' => 'submitted',
                'eta_submitted_at' => now(),
                'eta_response' => json_encode($response),
            ]);

            CheckETAStatus::dispatch($invoice->id)->delay(now()->addMinutes(2));
        } else {
            $invoice->update([
                'eta_status' => 'rejected',
                'eta_response' => json_encode($response),
            ]);

            throw new Exception('ETA rejected invoice: ' . json_encode($response['rejectedDocuments'] ?? []));
        }
    }
}
