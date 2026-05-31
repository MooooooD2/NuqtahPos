<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UnitConversion;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitConversionController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function show(Product $product): JsonResponse
    {
        return $this->success([
            'conversion' => $product->unitConversion?->load('purchaseUnit:id,name,abbreviation', 'saleUnit:id,name,abbreviation'),
        ]);
    }

    public function upsert(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'purchase_unit_id' => 'required|exists:units,id',
            'sale_unit_id' => 'required|exists:units,id|different:purchase_unit_id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $conv = UnitConversion::updateOrCreate(
            ['product_id' => $product->id],
            $data,
        );

        $this->audit('unit_conversion.upserted', 'Product', $product->id, $data);

        return $this->success([
            'conversion' => $conv->load('purchaseUnit:id,name,abbreviation', 'saleUnit:id,name,abbreviation'),
            'message' => __('pos.unit_conversion_saved'),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        UnitConversion::where('product_id', $product->id)->delete();
        $this->audit('unit_conversion.deleted', 'Product', $product->id, []);

        return $this->success(['message' => __('pos.unit_conversion_deleted')]);
    }
}
