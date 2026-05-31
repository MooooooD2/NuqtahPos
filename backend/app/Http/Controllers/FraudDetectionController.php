<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\SalesReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FraudDetectionController extends Controller
{
    /**
     * Aggregated fraud signals for the admin dashboard.
     *
     * Sources:
     *  - audit_logs (action LIKE 'anomaly.%') — real-time middleware signals
     *  - invoices created in off-hours — analytical signal
     *  - sales_returns grouped by cashier — excessive-return analytical signal
     *  - audit_logs (auth.login_failed) — brute-force signal
     *
     * @param Request $request Optional ?hours=N to override the lookback window
     */
    public function signals(Request $request): JsonResponse
    {
        $this->authorize('view_reports');

        $request->validate(['hours' => 'nullable|integer|min:1|max:720']);

        $hours = $request->integer('hours', config('security.anomaly.signals_lookback_hours', 24));
        $since = now()->subHours($hours);

        return response()->json([
            'period_hours' => $hours,
            'since' => $since->toIso8601String(),
            'realtime_signals' => $this->realtimeSignals($since),
            'off_hours_invoices' => $this->offHoursInvoices($since),
            'excessive_returns' => $this->excessiveReturns($since),
            'brute_force_attempts' => $this->bruteForceAttempts($since),
            'signal_summary' => $this->signalSummary($since),
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Real-time anomaly events written by the AnomalyDetection middleware.
     * Groups by action type and user, returns most recent 100 events.
     */
    private function realtimeSignals(Carbon $since): array
    {
        $events = AuditLog::where('action', 'like', 'anomaly.%')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['action', 'username', 'user_id', 'ip_address', 'changes', 'created_at']);

        $byType = $events->groupBy('action')->map(fn ($g) => [
            'count' => $g->count(),
            'users' => $g->pluck('username')->unique()->values(),
            'latest' => $g->first()->created_at->toIso8601String(),
            'events' => $g->take(10)->map(fn ($e) => [
                'username' => $e->username,
                'ip_address' => $e->ip_address,
                'details' => $e->changes,
                'at' => $e->created_at->toIso8601String(),
            ])->values(),
        ])->sortByDesc('count')->values();

        return [
            'total_events' => $events->count(),
            'by_type' => $byType,
        ];
    }

    /**
     * Invoices created during off-hours (configurable window, default 10pm–6am).
     * This is a server-side analytical signal — not caught by the middleware per-request.
     */
    private function offHoursInvoices(Carbon $since): array
    {
        $start = config('security.anomaly.off_hours_start', 22);
        $end = config('security.anomaly.off_hours_end', 6);

        // Build a HOUR() condition that spans midnight (e.g. hour >= 22 OR hour < 6)
        $rows = Invoice::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->where(
                fn ($q) => $q
                    ->whereRaw('HOUR(created_at) >= ?', [$start])
                    ->orWhereRaw('HOUR(created_at) < ?', [$end]),
            )
            ->with('cashier:id,username,full_name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'invoice_number', 'cashier_id', 'final_total', 'payment_method', 'created_at']);

        $byCashier = $rows->groupBy('cashier_id')->map(fn ($g) => [
            'cashier' => optional($g->first()->cashier)->username,
            'invoice_count' => $g->count(),
            'total_amount' => round($g->sum('final_total'), 2),
            'invoices' => $g->map(fn ($i) => [
                'invoice_number' => $i->invoice_number,
                'amount' => $i->final_total,
                'payment_method' => $i->payment_method,
                'at' => $i->created_at->toIso8601String(),
            ])->values(),
        ])->values();

        return [
            'window' => "{$start}:00–{$end}:00",
            'total_count' => $rows->count(),
            'by_cashier' => $byCashier,
        ];
    }

    /**
     * Cashiers with an unusually high number of completed returns in the period.
     */
    private function excessiveReturns(Carbon $since): array
    {
        $threshold = config('security.anomaly.excessive_returns_threshold', 5);

        $rows = SalesReturn::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->select('processed_by', 'processed_by_name', DB::raw('COUNT(*) as return_count'), DB::raw('SUM(total_amount) as total_amount'))
            ->groupBy('processed_by', 'processed_by_name')
            ->having('return_count', '>=', $threshold)
            ->orderByDesc('return_count')
            ->get();

        return [
            'threshold' => $threshold,
            'flagged_cashiers' => $rows->map(fn ($r) => [
                'user_id' => $r->processed_by,
                'username' => $r->processed_by_name,
                'return_count' => (int) $r->return_count,
                'total_amount' => round((float) $r->total_amount, 2),
            ])->values(),
        ];
    }

    /**
     * IPs and usernames with the most failed login attempts in the period.
     * Reuses the auth.login_failed entries already written by AuthController.
     */
    private function bruteForceAttempts(Carbon $since): array
    {
        $byIp = AuditLog::where('action', 'auth.login_failed')
            ->where('created_at', '>=', $since)
            ->selectRaw('ip_address, COUNT(*) as attempts, MAX(created_at) as last_attempt')
            ->groupBy('ip_address')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        $byUsername = AuditLog::where('action', 'auth.login_failed')
            ->where('created_at', '>=', $since)
            ->selectRaw('username, COUNT(*) as attempts, MAX(created_at) as last_attempt')
            ->groupBy('username')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        $threshold = config('security.anomaly.failed_logins_threshold', 10);

        return [
            'threshold' => $threshold,
            'top_ips' => $byIp->map(fn ($r) => [
                'ip' => $r->ip_address,
                'attempts' => (int) $r->attempts,
                'last_attempt' => $r->last_attempt,
                'flagged' => (int) $r->attempts >= $threshold,
            ])->values(),
            'top_usernames' => $byUsername->map(fn ($r) => [
                'username' => $r->username,
                'attempts' => (int) $r->attempts,
                'last_attempt' => $r->last_attempt,
                'flagged' => (int) $r->attempts >= $threshold,
            ])->values(),
        ];
    }

    /**
     * One-line counts for a dashboard widget — total signals by severity category.
     */
    private function signalSummary(Carbon $since): array
    {
        $anomalyCount = AuditLog::where('action', 'like', 'anomaly.%')->where('created_at', '>=', $since)->count();
        $authFailCount = AuditLog::where('action', 'auth.login_failed')->where('created_at', '>=', $since)->count();

        $offHoursStart = config('security.anomaly.off_hours_start', 22);
        $offHoursEnd = config('security.anomaly.off_hours_end', 6);
        $offHoursCount = Invoice::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->where(
                fn ($q) => $q
                    ->whereRaw('HOUR(created_at) >= ?', [$offHoursStart])
                    ->orWhereRaw('HOUR(created_at) < ?', [$offHoursEnd]),
            )->count();

        return [
            'anomaly_events' => $anomalyCount,
            'auth_failures' => $authFailCount,
            'off_hours_invoices' => $offHoursCount,
            'total_signals' => $anomalyCount + $authFailCount + $offHoursCount,
        ];
    }
}
