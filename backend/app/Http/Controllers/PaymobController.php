<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymobController extends Controller
{
    use ApiResponse;

    private string $baseUrl = 'https://accept.paymob.com/api';

    // ── Paymob API helpers ────────────────────────────────────────────────────

    private function authToken(): string
    {
        $res = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => config('services.paymob.api_key'),
        ]);
        if (! $res->successful() || ! $res->json('token')) {
            throw new RuntimeException('Paymob auth failed: ' . $res->body());
        }

        return $res->json('token');
    }

    private function createOrder(string $authToken, int $amountCents, string $ref): int
    {
        $res = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => 'EGP',
            'merchant_order_id' => $ref,
            'items' => [],
        ]);
        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException('Paymob order failed: ' . $res->body());
        }

        return (int) $res->json('id');
    }

    private function paymentKey(string $authToken, int $orderId, int $amountCents, int $integrationId, array $billing): string
    {
        $res = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $orderId,
            'billing_data' => $billing,
            'currency' => 'EGP',
            'integration_id' => $integrationId,
            'lock_order_when_paid' => true,
        ]);
        if (! $res->successful() || ! $res->json('token')) {
            throw new RuntimeException('Paymob payment key failed: ' . $res->body());
        }

        return $res->json('token');
    }

    private function billing(Tenant $tenant, ?string $phone = null): array
    {
        return [
            'apartment' => 'NA',
            'email' => 'billing@pos.local',
            'floor' => 'NA',
            'first_name' => $tenant->name,
            'street' => 'NA',
            'building' => 'NA',
            'phone_number' => $phone ? ('+2' . ltrim($phone, '0')) : '+201000000000',
            'shipping_method' => 'NA',
            'postal_code' => 'NA',
            'city' => 'Cairo',
            'country' => 'EGY',
            'last_name' => 'NA',
            'state' => 'NA',
        ];
    }

    // ── Checkout ──────────────────────────────────────────────────────────────

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|string|exists:mysql.plans,id',
            'billing_period' => 'required|in:monthly,annual',
            'method' => 'required|in:card,fawry,vodafone,etisalat,orange,instapay',
            'phone' => 'required_if:method,vodafone,etisalat,orange|nullable|string|regex:/^01[0-9]{9}$/',
        ]);

        $tenant = tenancy()->tenant;
        $plan = Plan::findOrFail($data['plan_id']);
        $isAnnual = $data['billing_period'] === 'annual';
        $price = $isAnnual ? $plan->annual_price : $plan->monthly_price;

        if (! $price || $price <= 0) {
            return $this->error(__('pos.invalid_plan_price'), 422);
        }

        $months = $isAnnual ? 12 : 1;
        $amountCents = (int) round($price * 100);
        $method = $data['method'];

        // InstaPay — manual transfer, no Paymob API call needed
        if ($method === 'instapay') {
            $ref = strtoupper(substr($tenant->id, 0, 8)) . '-' . strtoupper($plan->id) . '-' . $months . 'M';

            return $this->success([
                'type' => 'instapay',
                'account' => config('services.paymob.instapay_id', '01000000000'),
                'amount' => $price,
                'currency' => 'EGP',
                'reference' => $ref,
            ]);
        }

        $integrationMap = [
            'card' => (int) config('services.paymob.card_integration_id'),
            'fawry' => (int) config('services.paymob.fawry_integration_id'),
            'vodafone' => (int) config('services.paymob.vodafone_integration_id'),
            'etisalat' => (int) config('services.paymob.etisalat_integration_id'),
            'orange' => (int) config('services.paymob.orange_integration_id'),
        ];

        $ref = implode('|', [$tenant->id, $plan->id, $months, time()]);

        try {
            $authToken = $this->authToken();
            $orderId = $this->createOrder($authToken, $amountCents, $ref);
            $billing = $this->billing($tenant, $data['phone'] ?? null);
            $integrationId = $integrationMap[$method];
            $payKey = $this->paymentKey($authToken, $orderId, $amountCents, $integrationId, $billing);

            // ── Card → Paymob hosted iframe ──────────────────────────────────
            if ($method === 'card') {
                $iframeId = config('services.paymob.card_iframe_id');

                return $this->success([
                    'type' => 'iframe',
                    'url' => "https://accept.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$payKey}",
                ]);
            }

            // ── Fawry → generate reference number ────────────────────────────
            if ($method === 'fawry') {
                $payRes = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
                    'source' => ['identifier' => 'AGGREGATOR', 'subtype' => 'AGGREGATOR'],
                    'payment_token' => $payKey,
                ]);
                if (! $payRes->successful()) {
                    Log::error('Paymob Fawry pay failed', ['response' => $payRes->json()]);

                    return $this->error(__('pos.payment_error'), 500);
                }

                return $this->success([
                    'type' => 'fawry',
                    'reference' => $payRes->json('data.bill.reference_number') ?? $payRes->json('reference_number'),
                    'expires' => $payRes->json('data.bill.expiration_date'),
                    'amount' => $price,
                    'currency' => 'EGP',
                ]);
            }

            // ── Mobile wallet (Vodafone Cash / Etisalat / Orange) ────────────
            $payRes = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
                'source' => [
                    'identifier' => $data['phone'],
                    'subtype' => 'WALLET',
                ],
                'payment_token' => $payKey,
            ]);

            if (! $payRes->successful() || ! $payRes->json('redirect_url')) {
                Log::error('Paymob wallet pay failed', ['response' => $payRes->json(), 'method' => $method]);

                return $this->error(__('pos.payment_error'), 500);
            }

            return $this->success([
                'type' => 'redirect',
                'url' => $payRes->json('redirect_url'),
            ]);

        } catch (Throwable $e) {
            Log::error('Paymob checkout exception', ['msg' => $e->getMessage(), 'method' => $method]);

            return $this->error(__('pos.payment_error'), 500);
        }
    }

    // ── Callback (redirect from Paymob after card/wallet payment) ────────────

    public function callback(Request $request)
    {
        if ($request->query('success') !== 'true') {
            return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_failed')]);
        }

        if (! $this->verifyCallbackHmac($request->all())) {
            Log::warning('Paymob callback HMAC mismatch', $request->except('_token'));

            return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_failed')]);
        }

        $ref = $request->query('merchant_order_id', '');
        [$tenantId, $planId, $months] = array_pad(explode('|', $ref), 3, null);

        if ($tenantId && $planId && $months) {
            $this->activateSubscription($tenantId, $planId, (int) $months, $ref);
        }

        return view('subscription.success', [
            'provider' => 'paymob',
            'amount' => (float) $request->query('amount_cents', 0) / 100,
            'currency' => 'EGP',
            'txn_id' => $request->query('id', ''),
            'tenant' => tenancy()->tenant,
        ]);
    }

    // ── Webhook (server-to-server notification) ───────────────────────────────

    public function webhook(Request $request)
    {
        $payload = $request->json()->all();
        $obj = $payload['obj'] ?? [];

        if (! $this->verifyWebhookHmac($payload['hmac'] ?? '', $obj)) {
            Log::warning('Paymob webhook HMAC mismatch');

            return response('FAIL', 400);
        }

        if (($obj['success'] ?? false) !== true) {
            return response('OK', 200);
        }

        $ref = $obj['order']['merchant_order_id'] ?? '';
        [$tenantId, $planId, $months] = array_pad(explode('|', $ref), 3, null);

        if ($tenantId && $planId && $months) {
            $this->activateSubscription($tenantId, $planId, (int) $months, $ref);
        }

        return response('OK', 200);
    }

    // ── HMAC verification ─────────────────────────────────────────────────────

    private function verifyCallbackHmac(array $params): bool
    {
        $secret = config('services.paymob.hmac_secret');
        if (! $secret) {
            return false;
        }

        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured',
            'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided',
            'order', 'owner', 'pending', 'source_data.pan',
            'source_data.sub_type', 'source_data.type', 'success',
        ];

        $str = '';
        foreach ($fields as $f) {
            $str .= $params[$f] ?? '';
        }

        return hash_hmac('sha512', $str, $secret) === ($params['hmac'] ?? '');
    }

    private function verifyWebhookHmac(string $received, array $obj): bool
    {
        $secret = config('services.paymob.hmac_secret');
        if (! $secret) {
            return false;
        }

        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured',
            'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided',
            'order.id', 'owner', 'pending', 'source_data.pan',
            'source_data.sub_type', 'source_data.type', 'success',
        ];

        $str = '';
        foreach ($fields as $f) {
            if (str_contains($f, '.')) {
                [$k1, $k2] = explode('.', $f, 2);
                $str .= $obj[$k1][$k2] ?? '';
            } else {
                $str .= $obj[$f] ?? '';
            }
        }

        return hash_hmac('sha512', $str, $secret) === $received;
    }

    // ── Activate subscription ─────────────────────────────────────────────────

    private function activateSubscription(string $tenantId, string $planId, int $months, string $ref = ''): void
    {
        // Idempotency guard: callback and webhook can both fire for the same payment
        if ($ref) {
            $idempotencyKey = 'sub_activated_' . md5($ref);
            if (Cache::has($idempotencyKey)) {
                Log::info('Paymob subscription activation skipped (duplicate)', compact('ref'));

                return;
            }
            Cache::put($idempotencyKey, true, now()->addHours(48));
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        $base = ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isFuture())
            ? $tenant->subscription_ends_at
            : Carbon::now();

        $tenant->update([
            'plan' => $planId,
            'subscription_status' => 'active',
            'subscription_ends_at' => $base->addMonths($months),
            'is_active' => true,
        ]);

        Log::info('Paymob subscription activated', compact('tenantId', 'planId', 'months', 'ref'));
    }
}
