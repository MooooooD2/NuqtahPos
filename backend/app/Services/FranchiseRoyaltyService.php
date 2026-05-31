<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Phase 10 — Franchise Royalties Engine
 * Calculates and generates royalty statements for franchisees.
 */
class FranchiseRoyaltyService
{
    /**
     * Generate monthly royalty statements for all active agreements.
     */
    public function generateMonthlyStatements(int $year, int $month): array
    {
        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();
        $period = $periodStart->format('Y-m');

        $agreements = DB::table('franchise_agreements')
            ->where('status', 'active')
            ->where('start_date', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $periodStart))
            ->get();

        $generated = [];

        foreach ($agreements as $agreement) {
            try {
                $stmt = $this->generateStatement($agreement, $period, $periodStart, $periodEnd);
                $generated[] = $stmt;
            } catch (Throwable $e) {
                Log::error('FranchiseRoyalty: failed to generate statement', [
                    'agreement' => $agreement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $generated;
    }

    /**
     * Generate a single royalty statement for an agreement and period.
     */
    public function generateStatement(
        object $agreement,
        string $period,
        \Carbon\Carbon $start,
        \Carbon\Carbon $end,
    ): array {
        // Pull gross sales from the franchisee's tenant DB
        $grossSales = $this->fetchFranchiseeSales(
            $agreement->franchisee_tenant_id,
            $start,
            $end,
        );

        // Calculate royalty
        $royaltyAmount = $this->calculateRoyalty($agreement, $grossSales);
        $marketingFee = round($grossSales * ((float) $agreement->marketing_fee_rate / 100), 2);
        $totalDue = $royaltyAmount + $marketingFee;
        $dueDate = $end->copy()->addDays(15);

        $breakdown = $this->buildBreakdown($agreement, $grossSales, $royaltyAmount, $marketingFee);

        // Upsert statement
        $id = DB::table('royalty_statements')->updateOrInsert(
            ['franchise_agreement_id' => $agreement->id, 'period' => $period],
            [
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'gross_sales' => $grossSales,
                'royalty_amount' => $royaltyAmount,
                'marketing_fee' => $marketingFee,
                'total_due' => $totalDue,
                'balance_due' => $totalDue,     // recalculated after payments
                'status' => 'draft',
                'due_date' => $dueDate->toDateString(),
                'breakdown' => json_encode($breakdown),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return [
            'agreement_id' => $agreement->id,
            'period' => $period,
            'gross_sales' => $grossSales,
            'royalty_amount' => $royaltyAmount,
            'marketing_fee' => $marketingFee,
            'total_due' => $totalDue,
            'due_date' => $dueDate->toDateString(),
        ];
    }

    /**
     * Record a royalty payment against a statement.
     */
    public function recordPayment(int $statementId, float $amount, string $reference = ''): void
    {
        $stmt = DB::table('royalty_statements')->where('id', $statementId)->first();

        if (! $stmt) {
            throw new RuntimeException("Statement #{$statementId} not found.");
        }

        $paid = (float) $stmt->amount_paid + $amount;
        $balance = max(0, (float) $stmt->total_due - $paid);
        $status = $balance <= 0.01 ? 'paid' : 'invoiced';

        DB::table('royalty_statements')->where('id', $statementId)->update([
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
            'payment_reference' => $reference ?: $stmt->payment_reference,
            'updated_at' => now(),
        ]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function fetchFranchiseeSales(string $tenantId, \Carbon\Carbon $start, \Carbon\Carbon $end): float
    {
        // Query the franchisee's tenant DB using stancl/tenancy
        $tenant = \Stancl\Tenancy\Database\Models\Tenant::findOrFail($tenantId);

        return tenancy()->run($tenant, function () use ($start, $end) {
            return \App\Models\Invoice::whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['completed', 'paid'])
                ->sum('total');
        });
    }

    private function calculateRoyalty(object $agreement, float $grossSales): float
    {
        return match ($agreement->royalty_type) {
            'percentage' => round($grossSales * ((float) $agreement->royalty_rate / 100), 2),
            'fixed' => (float) $agreement->fixed_amount,
            'tiered' => $this->calculateTieredRoyalty($agreement, $grossSales),
            default => 0.0,
        };
    }

    private function calculateTieredRoyalty(object $agreement, float $grossSales): float
    {
        $tiers = json_decode($agreement->tiers ?? '[]', true);

        foreach ($tiers as $tier) {
            $min = (float) ($tier['min_sales'] ?? 0);
            $max = isset($tier['max_sales']) ? (float) $tier['max_sales'] : PHP_FLOAT_MAX;

            if ($grossSales >= $min && $grossSales < $max) {
                return round($grossSales * ((float) $tier['rate'] / 100), 2);
            }
        }

        return 0.0;
    }

    private function buildBreakdown(object $agreement, float $grossSales, float $royalty, float $marketing): array
    {
        return [
            'gross_sales' => $grossSales,
            'royalty_type' => $agreement->royalty_type,
            'royalty_rate' => $agreement->royalty_rate,
            'royalty_amount' => $royalty,
            'marketing_rate' => $agreement->marketing_fee_rate,
            'marketing_fee' => $marketing,
            'total_due' => $royalty + $marketing,
            'currency' => $agreement->currency_code,
        ];
    }
}
