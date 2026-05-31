<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    // ── Create Checkout Session ──────────────────────────────────────────────

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

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => config('services.stripe.currency', 'usd'),
                    'product_data' => [
                        'name' => $plan->name . ' — ' . ($isAnnual ? '12 months' : '1 month'),
                        'description' => implode(', ', array_slice($plan->features ?? [], 0, 3)),
                    ],
                    'unit_amount' => (int) round($price * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_period' => $data['billing_period'],
                'months' => $isAnnual ? 12 : 1,
            ],
            'success_url' => route('stripe.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('subscribe'),
            'customer_email' => auth()->user()?->email ?? null,
        ]);

        return response()->json(['success' => true, 'url' => $session->url]);
    }

    // ── Payment Success (redirect return) ───────────────────────────────────

    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return redirect()->route('subscribe');
        }

        try {
            $session = StripeSession::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent'],
            ]);
        } catch (Exception $e) {
            return redirect()->route('subscribe');
        }

        if ($session->payment_status === 'paid') {
            $planId = $session->metadata['plan_id'] ?? null;
            $billingPeriod = $session->metadata['billing_period'] ?? 'monthly';
            $months = (int) ($session->metadata['months'] ?? 1);

            // SECURITY: verify that the amount Stripe actually charged matches the plan price.
            // Protects against metadata tampering — an attacker cannot forge a $1 charge for
            // a 12-month subscription by editing the checkout metadata after the session is created.
            $plan = $planId ? Plan::find($planId) : null;
            if (! $plan) {
                Log::warning('Stripe success: plan not found', ['plan_id' => $planId, 'session_id' => $sessionId]);

                return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_plan_not_found')]);
            }

            $expectedPrice = $billingPeriod === 'annual' ? (float) $plan->annual_price : (float) $plan->monthly_price;
            $paidAmount = $session->amount_total / 100;   // Stripe stores amounts in cents

            if ($expectedPrice <= 0 || abs($paidAmount - $expectedPrice) > 0.01) {
                Log::warning('Stripe payment amount mismatch', [
                    'session_id' => $sessionId,
                    'plan_id' => $planId,
                    'expected_price' => $expectedPrice,
                    'paid_amount' => $paidAmount,
                    'tenant_id' => $session->metadata['tenant_id'] ?? null,
                ]);

                return redirect()->route('subscribe')->withErrors(['payment' => __('pos.payment_amount_mismatch')]);
            }

            $this->activateSubscription(
                $session->metadata['tenant_id'],
                $planId,
                $months,
            );
        }

        return view('subscription.success', [
            'session' => $session,
            'tenant' => tenancy()->tenant,
        ]);
    }

    // ── Stripe Webhook ───────────────────────────────────────────────────────

    public function webhook(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function handleCheckoutCompleted(object $session): void
    {
        if ($session->payment_status !== 'paid') {
            return;
        }

        $planId = $session->metadata->plan_id ?? null;
        $billingPeriod = $session->metadata->billing_period ?? 'monthly';
        $months = (int) ($session->metadata->months ?? 1);

        // SECURITY: server-side amount verification — same guard as the redirect handler.
        $plan = $planId ? Plan::find($planId) : null;
        if (! $plan) {
            Log::warning('Stripe webhook: plan not found', ['plan_id' => $planId]);

            return;
        }

        $expectedPrice = $billingPeriod === 'annual' ? (float) $plan->annual_price : (float) $plan->monthly_price;
        $paidAmount = $session->amount_total / 100;

        if ($expectedPrice <= 0 || abs($paidAmount - $expectedPrice) > 0.01) {
            Log::warning('Stripe webhook amount mismatch', [
                'plan_id' => $planId,
                'expected_price' => $expectedPrice,
                'paid_amount' => $paidAmount,
                'tenant_id' => $session->metadata->tenant_id ?? null,
            ]);

            return;
        }

        $this->activateSubscription(
            $session->metadata->tenant_id,
            $planId,
            $months,
        );
    }

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

        Log::info('Subscription activated', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'months' => $months,
            'ends_at' => $tenant->fresh()->subscription_ends_at,
        ]);
    }
}
