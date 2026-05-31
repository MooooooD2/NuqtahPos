<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRecipe;
use App\Services\RecipeService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(private RecipeService $recipeService) {}

    public function show(Product $product): JsonResponse
    {
        $recipe = ProductRecipe::where('product_id', $product->id)
            ->with('ingredient:id,name,quantity,unit_id')
            ->get()
            ->map(fn ($r) => [
                'ingredient_id' => $r->ingredient_id,
                'ingredient_name' => $r->ingredient?->name,
                'quantity' => $r->quantity,
                'available_stock' => $r->ingredient?->quantity,
            ]);

        return $this->success(['recipe' => $recipe]);
    }

    public function sync(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'ingredients' => 'required|array',
            'ingredients.*.ingredient_id' => 'required|integer|exists:products,id|different:' . $product->id,
            'ingredients.*.quantity' => 'required|numeric|min:0.001',
        ]);

        $this->recipeService->syncRecipe($product->id, $data['ingredients']);

        $this->audit('recipe.synced', 'Product', $product->id, [
            'ingredient_count' => count($data['ingredients']),
        ]);

        return $this->success(['message' => __('pos.recipe_saved')]);
    }
}
