<?php

namespace App\Http\Controllers;

use App\Models\CashbackRule;
use App\Models\CashbackTransaction;
use App\Services\CashbackService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashbackController extends Controller
{
    use ApiResponse;

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
     * When customer_id is omitted returns recent global transactions (for admin view).
     */
    public function history(Request $request): JsonResponse
    {
        $customerId = $request->integer('customer_id');
        $perPage = min((int) $request->input('per_page', 20), 100);

        if ($customerId) {
            return response()->json([
                'transactions' => $this->cashback->getHistory($customerId),
                'balance' => $this->cashback->getBalance($customerId),
            ]);
        }

        $paginator = CashbackTransaction::with('customer:id,name')
            ->latest()
            ->paginate($perPage);

        return $this->success([
            'data'  => $paginator->map(fn ($t) => [
                'id'            => $t->id,
                'customer_name' => $t->customer?->name ?? null,
                'type'          => $t->type,
                'amount'        => $t->amount,
                'balance_after' => $t->balance_after ?? '0',
                'created_at'    => $t->created_at,
            ]),
            'total' => $paginator->total(),
        ]);
    }

    /* ─── Cashback Rules CRUD (admin) ────────────────────────────────── */

    public function rules(): JsonResponse
    {
        return $this->success([
            'data' => CashbackRule::orderByDesc('is_active')->orderByDesc('created_at')->get(),
        ]);
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

    public function activateRule(int $id): JsonResponse
    {
        CashbackRule::query()->update(['is_active' => false]);
        $rule = CashbackRule::findOrFail($id);
        $rule->update(['is_active' => true]);

        return $this->success(['rule' => $rule]);
    }

    public function deleteRule(int $id): JsonResponse
    {
        $rule = CashbackRule::findOrFail($id);
        $rule->delete();

        return $this->success([]);
    }
}
