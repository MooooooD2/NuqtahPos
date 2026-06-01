<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(private PromotionService $promotionService) {}

    public function index(Request $request): JsonResponse
    {
        $promotions = Promotion::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '\%_') . '%'))
            ->when(! $request->boolean('with_inactive'), fn ($q) => $q->where('is_active', true))
            ->with('product:id,name')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return $this->success($promotions->toArray());
    }

    public function active(): JsonResponse
    {
        $today = now()->toDateString();

        $promotions = Promotion::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('product:id,name')
            ->limit(500)
            ->get();

        return $this->success(['promotions' => $promotions]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $this->authorize('create', Promotion::class);

        $promo = Promotion::create($request->validated());

        return $this->success(['promotion' => $promo], '', 201);
    }

    public function update(StorePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $this->authorize('update', $promotion);

        $promotion->update($request->validated());

        return $this->success(['promotion' => $promotion->fresh()]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->authorize('delete', $promotion);

        $promotion->delete();

        return $this->success([], __('pos.promotion_deleted'));
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.subtotal' => 'required|numeric|min:0',
        ]);

        $productIds = collect($request->items)->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $items = collect($request->items)->map(fn ($item) => array_merge($item, [
            'product' => $products->get($item['product_id']),
        ]))->toArray();

        $orderTotal = collect($items)->sum('subtotal');
        $result = $this->promotionService->apply($items, $orderTotal);

        return $this->success($result);
    }
}
