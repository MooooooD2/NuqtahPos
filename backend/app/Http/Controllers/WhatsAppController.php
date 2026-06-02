<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppController extends Controller
{
    use ApiResponse;

    public function __construct(private WhatsAppService $service) {}

    // ── Webhook (public — no auth) ───────────────────────────────────────────

    public function verifyWebhook(Request $request): Response
    {
        $challenge = $this->service->verifyWebhook(
            $request->get('hub_mode', ''),
            $request->get('hub_verify_token', ''),
            $request->get('hub_challenge', ''),
        );

        if ($challenge !== null) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function receiveWebhook(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (! $this->service->verifySignature($rawBody, $signature)) {
            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($request->all());

        return response('OK', 200);
    }

    // ── Admin API (auth required) ────────────────────────────────────────────

    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'nullable|string|max:20|regex:/^[+0-9\s\-]+$/',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
        ]);

        $logs = WhatsAppMessage::when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('message_type', $request->type))
            ->when($request->phone, fn ($q) => $q->where('to_number', 'like', '%' . $request->phone . '%'))
            ->latest()
            ->paginate(min((int) ($request->per_page ?? 25), 100));

        return $this->success($logs->toArray());
    }

    public function sendInvoice(Invoice $invoice): JsonResponse
    {
        $this->service->sendInvoice($invoice);

        return $this->success([], __('pos.wa_invoice_queued'));
    }

    public function sendDebtReminder(Customer $customer): JsonResponse
    {
        $this->service->sendDebtReminder($customer);

        return $this->success([], __('pos.wa_reminder_queued'));
    }

    public function sendBulkDebtReminders(Request $request): JsonResponse
    {
        $customers = Customer::where('balance', '>', 0)
            ->whereNotNull('phone')
            ->get();

        $count = 0;
        foreach ($customers as $customer) {
            $this->service->sendDebtReminder($customer);
            $count++;
        }

        return $this->success([], __('pos.wa_reminders_queued', ['count' => $count]));
    }

    public function sendPromotion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:customers,id',
            'vip_only' => 'boolean',
        ]);

        $query = Customer::whereNotNull('phone');
        if (! empty($data['customer_ids'])) {
            $query->whereIn('id', $data['customer_ids']);
        } elseif (! empty($data['vip_only'])) {
            $query->where('type', 'vip');
        }

        $count = $this->service->sendBulkPromotion($query->get(), $data['message']);

        return $this->success([], __('pos.wa_promotions_queued', ['count' => $count]));
    }

    public function stats(): JsonResponse
    {
        return $this->success([
            'data' => [
                'total_messages' => WhatsAppMessage::count(),
                'sent_today'     => WhatsAppMessage::where('direction', 'outbound')->whereDate('created_at', today())->count(),
                'inbound_today'  => WhatsAppMessage::where('direction', 'inbound')->whereDate('created_at', today())->count(),
                'failed_today'   => WhatsAppMessage::where('status', 'failed')->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
