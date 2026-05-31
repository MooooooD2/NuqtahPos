<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tap Payments — the #1 gateway in GCC (SA, UAE, KW, BH, QA, OM).
 * Docs: https://developers.tap.company
 */
class TapController extends Controller
{
    use ApiResponse;

    private string $baseUrl = 'https://api.tap.company/v2';

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . config('services.tap.secret'),
            'Content-Type' => 'application/json',
        ];
    }

    // ── Create Tap Charge ────────────────────────────────────────────────────

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|string|exists:mysql.plans,id',
            'billing_period' => 'required|in:monthly,annual',
        ]);

        $tenant = tenancy()->tenant;
        $plan = Plan::findOrFail($data['plan_id']);

        $isAnnual = $data['billing_period'] === 'annual';
        $price = $isAnnual ? $plan->annual_price : $plan->monthly_price;

        if (! $price || $price <= 0) {
            return $this->error(__('pos.invalid_plan_price'), 422);
        }

        $currency = config('services.tap.currency', 'SAR');
        $months = $isAnnual ? 12 : 1;
        $user = auth()->user();

        $response = Http::withHeaders($this->headers())->post("{$this->baseUrl}/charges", [
            'amount' => (float) $price,
            'currency' => $currency,
            'description' => $plan->name . ' — ' . ($isAnnual ? '12 months' : '1 month'),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_period' => $data['billing_period'],
                'months' => $months,
            ],
            'source' => ['id' => 'src_all'],
            'customer' => [
                'first_name' => $user?->full_name ?? $tenant->name,
                'email' => $user?->email ?? null,
            ],
            'redirect' => [
                'url' => route('tap.callback'),
            ],
            'reference' => [
                'merchant' => "{$tenant->id}|{$plan->id}|{$months}",
            ],
        ]);

        if ($response->successful()) {
            $charge = $response->json();
            $redirectUrl = $charge['transaction']['url'] ?? null;

            if ($redirectUrl) {
                return $this->success(['url' => $redirectUrl]);
            }
        }

        Log::error('Tap charge creation failed', ['response' => $response->json()]);

        return $this->error(__('pos.payment_error'), 500);
    }

    // ── Callback after Tap payment ───────────────────────────────────────────

    public function callback(Request $request)
    {
        $tapId = $request->query('tap_id');

        if (! $tapId) {
            return redirect()->route('subscribe');
        }

        $response = Http::withHeaders($this->headers())->get("{$this->baseUrl}/charges/{$tapId}");

        if (! $response->successful()) {
            return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_failed')]);
        }

        $charge = $response->json();

        if (($charge['status'] ?? '') === 'CAPTURED') {
            $ref = $charge['reference']['merchant'] ?? '';
            [$tenantId, $planId, $months] = array_pad(explode('|', $ref), 3, null);

            if ($tenantId && $planId && $months) {
                $this->activateSubscription($tenantId, $planId, (int) $months);
            }

            return view('subscription.success', [
                'provider' => 'tap',
                'amount' => $charge['amount'] ?? 0,
                'currency' => $charge['currency'] ?? 'SAR',
                'txn_id' => $charge['id'] ?? '',
                'tenant' => tenancy()->tenant,
            ]);
        }

        return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_failed')]);
    }

    // ── Webhook (optional — for extra reliability) ───────────────────────────

    public function webhook(Request $request)
    {
        $chargeId = $request->json('id');

        if (! $chargeId) {
            return response('OK', 200);
        }

        // Verify the charge status directly via the Tap API instead of trusting
        // the webhook payload — prevents subscription activation via forged requests.
        $apiResponse = Http::withHeaders($this->headers())->get("{$this->baseUrl}/charges/{$chargeId}");

        if (! $apiResponse->successful()) {
            Log::warning('Tap webhook: failed to verify charge', ['charge_id' => $chargeId]);

            return response('OK', 200);
        }

        $charge = $apiResponse->json();

        if (($charge['status'] ?? '') !== 'CAPTURED') {
            return response('OK', 200);
        }

        $ref = $charge['reference']['merchant'] ?? '';
        [$tenantId, $planId, $months] = array_pad(explode('|', $ref), 3, null);

        if ($tenantId && $planId && $months) {
            $this->activateSubscription($tenantId, $planId, (int) $months);
        }

        return response('OK', 200);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function activateSubscription(string $tenantId, string $planId, int $months): void
    {
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

        Log::info('Tap subscription activated', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'months' => $months,
        ]);
    }
}
