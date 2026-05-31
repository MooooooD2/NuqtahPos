<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Throwable;

class PayPalController extends Controller
{
    use ApiResponse;

    // ── Create PayPal Order ──────────────────────────────────────────────────

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

        $currency = config('services.paypal.currency', 'USD');
        $months = $isAnnual ? 12 : 1;

        try {
            $paypal = new PayPalClient;
            $paypal->setApiCredentials((array) config('paypal', []));
            $paypal->getAccessToken();

            $order = $paypal->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => "{$tenant->id}|{$plan->id}|{$months}",
                        'description' => $plan->name . ' — ' . ($isAnnual ? '12 months' : '1 month'),
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($price, 2, '.', ''),
                        ],
                    ],
                ],
                'application_context' => [
                    'return_url' => route('paypal.success'),
                    'cancel_url' => route('subscribe'),
                    'brand_name' => config('app.name', 'NuqtahPOS'),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

            if (isset($order['id'])) {
                $approvalUrl = collect($order['links'])
                    ->firstWhere('rel', 'approve')['href'] ?? null;

                if ($approvalUrl) {
                    return $this->success(['url' => $approvalUrl]);
                }
            }

            Log::error('PayPal order creation failed', ['response' => $order]);

            return $this->error(__('pos.payment_error'), 500);

        } catch (Throwable $e) {
            Log::error('PayPal exception', ['message' => $e->getMessage()]);

            return $this->error(__('pos.payment_error'), 500);
        }
    }

    // ── Capture after PayPal approval ───────────────────────────────────────

    public function returnUrl(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect()->route('subscribe');
        }

        try {
            $paypal = new PayPalClient;
            $paypal->setApiCredentials((array) config('paypal', []));
            $paypal->getAccessToken();

            $capture = $paypal->capturePaymentOrder($token);

            if (($capture['status'] ?? '') === 'COMPLETED') {
                $ref = $capture['purchase_units'][0]['reference_id'] ?? '';
                [$tenantId, $planId, $months] = array_pad(explode('|', $ref), 3, null);

                if ($tenantId && $planId && $months) {
                    // SECURITY: verify that PayPal actually charged the correct plan price.
                    // An attacker cannot place a $1 order then tamper with reference_id to
                    // claim 12 months — we compare the captured amount against the DB price.
                    $plan = Plan::find($planId);
                    $months = (int) $months;

                    if (! $plan) {
                        Log::warning('PayPal return: plan not found', ['plan_id' => $planId]);

                        return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_plan_not_found')]);
                    }

                    $isAnnual = $months >= 12;
                    $expectedPrice = $isAnnual ? (float) $plan->annual_price : (float) $plan->monthly_price;
                    $capturedAmt = (float) ($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);

                    if ($expectedPrice <= 0 || abs($capturedAmt - $expectedPrice) > 0.01) {
                        Log::warning('PayPal payment amount mismatch', [
                            'plan_id' => $planId,
                            'expected_price' => $expectedPrice,
                            'captured_amount' => $capturedAmt,
                            'tenant_id' => $tenantId,
                        ]);

                        return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_amount_mismatch')]);
                    }

                    $this->activateSubscription($tenantId, $planId, $months);
                }

                return view('subscription.success', [
                    'provider' => 'paypal',
                    'amount' => $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0,
                    'currency' => $capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? 'USD',
                    'txn_id' => $capture['id'] ?? '',
                    'tenant' => tenancy()->tenant,
                ]);
            }

        } catch (Throwable $e) {
            Log::error('PayPal capture exception', ['message' => $e->getMessage()]);
        }

        return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_failed')]);
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

        Log::info('PayPal subscription activated', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'months' => $months,
        ]);
    }
}
