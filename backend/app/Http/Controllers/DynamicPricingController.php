<?php

namespace App\Http\Controllers;

use App\Models\PriceRule;
use App\Services\DynamicPricingService;
use App\Traits\ApiResponse;
use BadMethodCallException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DynamicPricingController extends Controller
{
    use ApiResponse;
    public function __construct(private DynamicPricingService $pricing) {}

    /* ─── Web View ───────────────────────────────────────────────────── */

    public function index(): \Illuminate\View\View
    {
        return view('pricing-rules.index');
    }

    /* ─── API ────────────────────────────────────────────────────────── */

    public function all(): JsonResponse
    {
        return $this->success(['data' => $this->pricing->getAllRulesWithStatus()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRule($request);
        $rule = PriceRule::create($data);
        $this->clearCache();

        return response()->json(['rule' => $rule], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = PriceRule::findOrFail($id);
        $data = $this->validateRule($request);
        $rule->update($data);
        $this->clearCache();

        return response()->json(['rule' => $rule]);
    }

    public function destroy(int $id): JsonResponse
    {
        PriceRule::findOrFail($id)->delete();
        $this->clearCache();

        return response()->json(['message' => 'Deleted']);
    }

    public function toggle(int $id): JsonResponse
    {
        $rule = PriceRule::findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
        $this->clearCache();

        return response()->json(['rule' => $rule, 'is_active' => $rule->is_active]);
    }

    /**
     * Evaluate pricing for one or more products (used by POS).
     */
    public function evaluate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'customer_group_id' => 'nullable|integer',
        ]);

        $results = $this->pricing->evaluateBatch($data['items'], $data['customer_group_id'] ?? null);

        return response()->json([
            'prices' => $results,
            'happy_hour_active' => $this->pricing->isHappyHourActive(),
        ]);
    }

    /* ─── Private ────────────────────────────────────────────────────── */

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'rule_type' => 'required|in:happy_hour,bulk_discount,day_of_week,loyalty_tier,category,flat_price',
            'discount_type' => 'required|in:percentage,fixed_amount,new_price',
            'discount_value' => 'required|numeric|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',
            'category_ids' => 'nullable|array',
            'customer_group_id' => 'nullable|integer',
            'time_start' => 'nullable|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:1,7',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'min_quantity' => 'nullable|numeric|min:0',
            'priority' => 'nullable|integer|min:1|max:100',
            'is_active' => 'nullable|boolean',
            'stackable' => 'nullable|boolean',
        ]);
    }

    private function clearCache(): void
    {
        try {
            Cache::tags(['price_rules'])->flush();
        } catch (BadMethodCallException $e) {
            Cache::flush();
        }
    }
}
