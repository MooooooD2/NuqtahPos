<?php

namespace App\Services;

use App\Models\CashbackRule;
use App\Models\CashbackTransaction;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cashback System — Phase 8: Marketing & CRM
 *
 * Customers earn cashback % on purchases; can redeem on future purchases.
 *
 * Note: cashback_balance is NOT in Customer::$fillable.
 * All balance mutations go through DB::table() to intentionally bypass the
 * mass-assignment guard — only this service may change the field.
 */
class CashbackService
{
    /**
     * Earn cashback after a successful invoice.
     */
    public function earnFromInvoice(Invoice $invoice): ?CashbackTransaction
    {
        if (! $invoice->customer_id) {
            return null;
        }

        $rule = CashbackRule::active()->orderByDesc('percentage')->first();
        if (! $rule) {
            return null;
        }

        $amount = $rule->calculate((float) $invoice->final_total);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $amount) {
            $customer = Customer::lockForUpdate()->findOrFail($invoice->customer_id);
            $newBalance = round($customer->cashback_balance + $amount, 2);

            // cashback_balance is NOT in $fillable — use DB::table to bypass the guard
            DB::table('customers')->where('id', $customer->id)->update(['cashback_balance' => $newBalance]);
            $invoice->update(['cashback_earned' => $amount]);

            return CashbackTransaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'type' => 'earned',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => "Earned from invoice #{$invoice->invoice_number}",
            ]);
        });
    }

    /**
     * Redeem cashback during checkout.
     *
     * Throws a ValidationException if amount exceeds the customer's balance.
     *
     * @throws ValidationException
     *
     * @return float Actual amount redeemed
     */
    public function redeem(int $customerId, float $amount, ?int $invoiceId = null): float
    {
        return DB::transaction(function () use ($customerId, $amount, $invoiceId) {
            $customer = Customer::lockForUpdate()->findOrFail($customerId);

            $available = round((float) $customer->cashback_balance, 2);
            $amount = round($amount, 2);

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'amount' => [__('pos.insufficient_cashback_balance')],
                ]);
            }

            if ($amount <= 0) {
                return 0;
            }

            $newBalance = round($available - $amount, 2);

            // cashback_balance is NOT in $fillable — use DB::table to bypass the guard
            DB::table('customers')->where('id', $customer->id)->update(['cashback_balance' => $newBalance]);

            if ($invoiceId) {
                Invoice::where('id', $invoiceId)->increment('cashback_redeemed', $amount);
            }

            CashbackTransaction::create([
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'type' => 'redeemed',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Redeemed at checkout' . ($invoiceId ? " (Invoice #{$invoiceId})" : ''),
            ]);

            return $amount;
        });
    }

    /**
     * Get customer cashback balance.
     */
    public function getBalance(int $customerId): float
    {
        return (float) Customer::where('id', $customerId)->value('cashback_balance') ?? 0;
    }

    /**
     * Get transaction history for a customer.
     */
    public function getHistory(int $customerId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return CashbackTransaction::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Reverse cashback earned from a returned invoice.
     */
    public function reverseFromReturn(Invoice $invoice): void
    {
        if (! $invoice->customer_id || $invoice->cashback_earned <= 0) {
            return;
        }

        DB::transaction(function () use ($invoice) {
            $customer = Customer::lockForUpdate()->findOrFail($invoice->customer_id);
            $amount = min($invoice->cashback_earned, $customer->cashback_balance);

            if ($amount <= 0) {
                return;
            }

            $newBalance = round($customer->cashback_balance - $amount, 2);

            // cashback_balance is NOT in $fillable — use DB::table to bypass the guard
            DB::table('customers')->where('id', $customer->id)->update(['cashback_balance' => $newBalance]);

            CashbackTransaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'type' => 'adjusted',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => "Reversed: return of invoice #{$invoice->invoice_number}",
            ]);
        });
    }

    /**
     * Get the active cashback rate percentage.
     */
    public function getActiveRate(): float
    {
        $rule = CashbackRule::active()->orderByDesc('percentage')->first();

        return $rule ? $rule->percentage : 0;
    }
}
