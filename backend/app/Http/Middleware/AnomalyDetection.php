<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AnomalyDetection
{
    // How many anomaly strikes before a temporary block is issued.
    private const BLOCK_THRESHOLD = 5;

    // How long (seconds) the temporary block lasts.
    private const BLOCK_DURATION = 300; // 5 minutes

    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is already temporarily blocked before processing the request.
        if (auth()->check() && $this->isBlocked(auth()->id())) {
            $this->writeAnomalyLog('anomaly.blocked_request_rejected', $request, [
                'blocked_until' => Cache::get($this->blockKey(auth()->id()) . '_until'),
            ]);

            abort(429, 'تم تعليق حسابك مؤقتاً بسبب نشاط مشبوه. يرجى المحاولة لاحقاً.');
        }

        $response = $next($request);

        try {
            $this->detectRapidRequests($request);

            if ($request->is('api/invoices') && $request->isMethod('POST') && $response->getStatusCode() === 201) {
                $this->detectLargeInvoice($request, $response);
                $this->detectOffHoursTransaction($request, $response);
            }

            $this->detectDiscountCapViolation($request, $response);

        } catch (Throwable) {
            // Anomaly detection must never interrupt a business operation
        }

        return $response;
    }

    // ── Strike / block helpers ───────────────────────────────────────────────

    private function blockKey(int|string $userId): string
    {
        return 'anomaly_block_' . $userId;
    }

    private function isBlocked(int|string $userId): bool
    {
        return (bool) Cache::get($this->blockKey($userId));
    }

    /**
     * Increment the anomaly strike counter for this user.
     * If the threshold is reached, issue a temporary block and log it.
     */
    private function strike(Request $request, string $reason): void
    {
        if (! auth()->check()) {
            return;
        }

        $userId = auth()->id();
        $strikeKey = 'anomaly_strikes_' . $userId;

        Cache::add($strikeKey, 0, 3600);
        $strikes = Cache::increment($strikeKey);

        if ($strikes >= self::BLOCK_THRESHOLD) {
            $blockedUntil = now()->addSeconds(self::BLOCK_DURATION)->toIso8601String();

            Cache::put($this->blockKey($userId), true, self::BLOCK_DURATION);
            Cache::put($this->blockKey($userId) . '_until', $blockedUntil, self::BLOCK_DURATION);
            Cache::forget($strikeKey);

            $this->writeAnomalyLog('anomaly.user_temporarily_blocked', $request, [
                'reason' => $reason,
                'blocked_until' => $blockedUntil,
                'duration_sec' => self::BLOCK_DURATION,
            ]);
        }
    }

    // ── Detectors ────────────────────────────────────────────────────────────

    private function detectRapidRequests(Request $request): void
    {
        if (! auth()->check()) {
            return;
        }

        $threshold = (int) config('security.anomaly.requests_per_minute', 100);
        $key = 'req_count_' . auth()->id() . '_' . now()->format('Hi');

        Cache::add($key, 0, 60);
        $count = Cache::increment($key);

        if ($count > $threshold) {
            $this->writeAnomalyLog('anomaly.rapid_requests', $request, [
                'count' => $count,
                'threshold' => $threshold,
                'url' => $request->path(),
            ]);
            $this->strike($request, 'rapid_requests');
        }
    }

    private function detectLargeInvoice(Request $request, Response $response): void
    {
        $threshold = (int) config('security.anomaly.invoice_amount_threshold', 50000);
        $body = json_decode($response->getContent(), true);
        $total = $body['invoice']['final_total'] ?? 0;

        if ($total >= $threshold) {
            $this->writeAnomalyLog('anomaly.large_invoice', $request, [
                'invoice_total' => $total,
                'threshold' => $threshold,
                'payment_method' => $request->input('payment_method'),
            ]);
            // Large invoice is informational only — no strike (may be legitimate).
        }
    }

    private function detectOffHoursTransaction(Request $request, Response $response): void
    {
        if (! auth()->check()) {
            return;
        }

        $start = (int) config('security.anomaly.off_hours_start', 22);
        $end = (int) config('security.anomaly.off_hours_end', 6);
        $hour = (int) now()->format('G');

        $isOffHours = $hour >= $start || $hour < $end;
        if (! $isOffHours) {
            return;
        }

        $body = json_decode($response->getContent(), true);
        $total = $body['invoice']['final_total'] ?? 0;

        $this->writeAnomalyLog('anomaly.off_hours_transaction', $request, [
            'hour' => $hour,
            'invoice_total' => $total,
            'payment_method' => $request->input('payment_method'),
            'off_hours_window' => "{$start}:00–{$end}:00",
        ]);
        // Off-hours is informational only — no strike (night shifts are legitimate).
    }

    private function detectDiscountCapViolation(Request $request, Response $response): void
    {
        if (! auth()->check()) {
            return;
        }
        if (! $request->is('api/invoices') || ! $request->isMethod('POST')) {
            return;
        }
        if ($response->getStatusCode() !== 422) {
            return;
        }

        $body = json_decode($response->getContent(), true);
        $message = $body['message'] ?? '';

        if (! str_contains($message, 'discount') && ! str_contains($message, 'خصم')) {
            return;
        }

        $discountKey = 'discount_attempt_' . auth()->id();
        $attemptCount = Cache::increment($discountKey, 1);
        Cache::expire($discountKey, 3600);

        if ($attemptCount >= 3) {
            $this->writeAnomalyLog('anomaly.repeated_discount_attempts', $request, [
                'attempts' => $attemptCount,
            ]);
            $this->strike($request, 'repeated_discount_violations');
        }
    }

    // ── Logging ──────────────────────────────────────────────────────────────

    private function writeAnomalyLog(string $action, Request $request, array $context = []): void
    {
        $userId = Auth::id();
        $username = Auth::user()?->username ?? 'unknown';

        $payload = array_merge($context, [
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            Log::channel('audit')->warning($action, array_merge(
                ['user_id' => $userId, 'username' => $username],
                $payload,
            ));
        } catch (Throwable) {
        }

        try {
            AuditLog::create([
                'action' => $action,
                'model' => null,
                'record_id' => null,
                'user_id' => $userId,
                'username' => $username,
                'ip_address' => filter_var($request->ip(), FILTER_VALIDATE_IP) ? $request->ip() : 'invalid',
                'user_agent' => substr(preg_replace('/[\x00-\x1F\x7F]/', '', $request->userAgent() ?? ''), 0, 250),
                'changes' => $payload,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
        }
    }
}
