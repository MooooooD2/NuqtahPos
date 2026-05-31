<?php

namespace App\Http\Controllers;

use App\Models\CashbackRule;
use App\Models\CashbackTransaction;
use App\Services\CashbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashbackController extends Controller
{
    public function __construct(private CashbackService $cashback) {}

    /**
     * Cashback management page (web).
     */
    public function indexPage()
    {
        $activeRule = CashbackRule::where('is_active', true)->latest()->first();
        $allRules = CashbackRule::orderByDesc('is_active')->orderByDesc('created_at')->get();
        $totalEarned = CashbackTransaction::where('type', 'earned')->sum('amount');
        $totalRedeemed = CashbackTransaction::where('type', 'redeemed')->sum('amount');
        $totalBalance = \App\Models\Customer::sum('cashback_balance');
        $recentTxns = CashbackTransaction::with('customer')
            ->latest()
            ->limit(20)
            ->get();

        return view('cashback.index', compact(
            'activeRule',
            'allRules',
            'totalEarned',
            'totalRedeemed',
            'totalBalance',
            'recentTxns',
        ));
    }

    /**
     * Get customer cashback balance.
     */
    public function balance(int $customerId): JsonResponse
    {
        $balance = $this->cashback->getBalance($customerId);
        $rate = $this->cashback->getActiveRate();

        return response()->json([
            'customer_id' => $customerId,
            'balance' => $balance,
            'active_rate' => $rate,
        ]);
    }

    /**
     * Redeem cashback for a customer.
     */
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'invoice_id' => 'nullable|integer',
        ]);

        $redeemed = $this->cashback->redeem(
            $data['customer_id'],
            $data['amount'],
            $data['invoice_id'] ?? null,
        );

        return response()->json([
            'redeemed' => $redeemed,
            'new_balance' => $this->cashback->getBalance($data['customer_id']),
        ]);
    }

    /**
     * Cashback transaction history.
     */
    public function history(Request $request): JsonResponse
    {
        $customerId = $request->integer('customer_id');

        if (! $customerId) {
            return response()->json(['error' => 'customer_id required'], 422);
        }

        return response()->json([
            'transactions' => $this->cashback->getHistory($customerId),
            'balance' => $this->cashback->getBalance($customerId),
        ]);
    }

    /* ─── Cashback Rules CRUD (admin) ────────────────────────────────── */

    public function rules(): JsonResponse
    {
        return response()->json(CashbackRule::orderByDesc('is_active')->get());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'percentage' => 'required|numeric|min:0.01|max:100',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_cashback' => 'nullable|numeric|min:0',
        ]);

        // Deactivate previous rules
        CashbackRule::query()->update(['is_active' => false]);

        $rule = CashbackRule::create(array_merge($data, ['is_active' => true]));

        return response()->json(['rule' => $rule], 201);
    }
}
