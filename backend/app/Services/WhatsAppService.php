<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage as SendJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    private string $apiBase;

    private string $phoneNumberId;

    private string $accessToken;

    private string $language;

    public function __construct()
    {
        $this->phoneNumberId = config('whatsapp.phone_number_id', '');
        $this->accessToken = config('whatsapp.access_token', '');
        $this->language = config('whatsapp.language', 'ar');
        $this->apiBase = config('whatsapp.base_url') . '/' . config('whatsapp.api_version');
    }

    public function isEnabled(): bool
    {
        return config('whatsapp.enabled', false)
            && ! empty($this->phoneNumberId)
            && ! empty($this->accessToken);
    }

    // ── Public send methods (queued) ─────────────────────────────────────────

    public function sendInvoice(Invoice $invoice): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $phone = optional($invoice->customer)->phone;
        if (! $phone) {
            return;
        }

        $log = $this->createLog([
            'direction' => 'outbound',
            'to_number' => $this->normalizePhone($phone),
            'message_type' => 'invoice',
            'related_type' => 'invoice',
            'related_id' => $invoice->id,
            'template_name' => config('whatsapp.templates.invoice'),
        ]);

        SendJob::dispatch($log->id)->onQueue('whatsapp');
    }

    public function sendDebtReminder(Customer $customer): void
    {
        if (! $this->isEnabled() || ! $customer->phone) {
            return;
        }
        if ($customer->balance <= 0) {
            return;
        }

        $log = $this->createLog([
            'direction' => 'outbound',
            'to_number' => $this->normalizePhone($customer->phone),
            'message_type' => 'reminder',
            'related_type' => 'customer',
            'related_id' => $customer->id,
            'template_name' => config('whatsapp.templates.debt_reminder'),
            'message_body' => __('pos.wa_debt_reminder_body', [
                'name' => $customer->name,
                'balance' => number_format($customer->balance, 2),
            ]),
        ]);

        SendJob::dispatch($log->id)->onQueue('whatsapp');
    }

    public function sendDailySummary(array $stats): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $managerPhone = config('whatsapp.manager_phone');
        if (! $managerPhone) {
            return;
        }

        $log = $this->createLog([
            'direction' => 'outbound',
            'to_number' => $this->normalizePhone($managerPhone),
            'message_type' => 'alert',
            'template_name' => config('whatsapp.templates.daily_summary'),
            'message_body' => __('pos.wa_daily_summary_body', $stats),
        ]);

        SendJob::dispatch($log->id)->onQueue('whatsapp');
    }

    public function sendLowStockAlert(array $products): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $managerPhone = config('whatsapp.manager_phone');
        if (! $managerPhone) {
            return;
        }

        $productNames = collect($products)->pluck('name')->join(', ');
        $log = $this->createLog([
            'direction' => 'outbound',
            'to_number' => $this->normalizePhone($managerPhone),
            'message_type' => 'alert',
            'template_name' => config('whatsapp.templates.low_stock'),
            'message_body' => __('pos.wa_low_stock_body', ['products' => $productNames]),
        ]);

        SendJob::dispatch($log->id)->onQueue('whatsapp');
    }

    public function sendLargeInvoiceAlert(Invoice $invoice): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        $managerPhone = config('whatsapp.manager_phone');
        if (! $managerPhone) {
            return;
        }
        if ($invoice->final_total < config('whatsapp.large_invoice_threshold', 5000)) {
            return;
        }

        $log = $this->createLog([
            'direction' => 'outbound',
            'to_number' => $this->normalizePhone($managerPhone),
            'message_type' => 'alert',
            'template_name' => config('whatsapp.templates.large_invoice'),
            'message_body' => __('pos.wa_large_invoice_body', [
                'number' => $invoice->invoice_number,
                'total' => number_format($invoice->final_total, 2),
                'cashier' => $invoice->cashier_name,
            ]),
        ]);

        SendJob::dispatch($log->id)->onQueue('whatsapp');
    }

    public function sendPromotion(Customer $customer, string $message): void
    {
        if (! $this->isEnabled() || ! $customer->phone) {
            return;
        }

        $log = $this->createLog([
            'direction' => 'outbound',
            'to_number' => $this->normalizePhone($customer->phone),
            'message_type' => 'promotion',
            'related_type' => 'customer',
            'related_id' => $customer->id,
            'template_name' => config('whatsapp.templates.promotion'),
            'message_body' => $message,
        ]);

        SendJob::dispatch($log->id)->onQueue('whatsapp');
    }

    public function sendBulkPromotion(iterable $customers, string $message): int
    {
        $count = 0;
        foreach ($customers as $customer) {
            $this->sendPromotion($customer, $message);
            $count++;
        }

        return $count;
    }

    // ── Actual HTTP send (called by the Job) ─────────────────────────────────

    public function dispatchMessage(WhatsAppMessage $log): void
    {
        try {
            $payload = $this->buildPayload($log);
            $url = "{$this->apiBase}/{$this->phoneNumberId}/messages";

            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post($url, $payload);

            if ($response->successful()) {
                $waId = data_get($response->json(), 'messages.0.id');
                $log->update([
                    'status' => 'sent',
                    'wa_message_id' => $waId,
                    'sent_at' => now(),
                ]);
            } else {
                $this->markFailed($log, $response->body());
            }
        } catch (Throwable $e) {
            $this->markFailed($log, $e->getMessage());
            Log::error('whatsapp.send_failed', ['log_id' => $log->id, 'error' => $e->getMessage()]);
        }
    }

    // ── Webhook handling ─────────────────────────────────────────────────────

    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        if ($mode === 'subscribe' && $token === config('whatsapp.verify_token')) {
            return $challenge;
        }

        return null;
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = config('whatsapp.app_secret');
        if (empty($secret)) {
            return false;
        }

        if (! $signatureHeader || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    public function handleWebhook(array $payload): void
    {
        $entries = data_get($payload, 'entry', []);
        foreach ($entries as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $this->processChange($change);
            }
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function processChange(array $change): void
    {
        // Status updates (delivered, read)
        foreach (data_get($change, 'value.statuses', []) as $status) {
            $waId = data_get($status, 'id');
            $state = data_get($status, 'status'); // sent/delivered/read/failed
            $ts = data_get($status, 'timestamp');

            $log = WhatsAppMessage::where('wa_message_id', $waId)->first();
            if (! $log) {
                continue;
            }

            $updates = ['status' => $state];
            if ($state === 'delivered') {
                $updates['delivered_at'] = now();
            }
            if ($state === 'read') {
                $updates['read_at'] = now();
            }
            $log->update($updates);
        }

        // Inbound messages
        foreach (data_get($change, 'value.messages', []) as $msg) {
            $from = data_get($msg, 'from');
            $text = data_get($msg, 'text.body') ?? data_get($msg, 'interactive.button_reply.title');

            WhatsAppMessage::create([
                'direction' => 'inbound',
                'from_number' => $from,
                'to_number' => data_get($change, 'value.metadata.phone_number_id'),
                'message_type' => 'inbound',
                'message_body' => $text,
                'wa_message_id' => data_get($msg, 'id'),
                'status' => 'read',
            ]);

            Log::info('whatsapp.inbound', ['from' => $from, 'text' => $text]);
        }
    }

    private function buildPayload(WhatsAppMessage $log): array
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'to' => $log->to_number,
        ];

        if ($log->template_name) {
            return array_merge($base, [
                'type' => 'template',
                'template' => [
                    'name' => $log->template_name,
                    'language' => ['code' => $this->language],
                    'components' => $this->buildTemplateComponents($log),
                ],
            ]);
        }

        return array_merge($base, [
            'type' => 'text',
            'text' => ['body' => $log->message_body, 'preview_url' => false],
        ]);
    }

    private function buildTemplateComponents(WhatsAppMessage $log): array
    {
        if (! $log->message_body) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => [['type' => 'text', 'text' => $log->message_body]],
        ]];
    }

    private function createLog(array $data): WhatsAppMessage
    {
        return WhatsAppMessage::create(array_merge(['status' => 'queued'], $data));
    }

    private function markFailed(WhatsAppMessage $log, string $error): void
    {
        $log->update(['status' => 'failed', 'error_message' => substr($error, 0, 500)]);
    }

    private function normalizePhone(string $phone): string
    {
        // Strip spaces, dashes, parens; ensure no leading +
        $clean = preg_replace('/[\s\-\(\)]/', '', $phone);

        return ltrim($clean, '+');
    }
}
