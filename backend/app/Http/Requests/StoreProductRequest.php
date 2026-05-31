<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Warehouse managers need add_product; cashiers can quick-add from the POS (view_pos).
        return $user->can('add_product') || $user->can('view_pos');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => strip_tags($this->name)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:9999999',
            'wholesale_price' => 'nullable|numeric|min:0|max:9999999',
            'vip_price' => 'nullable|numeric|min:0|max:9999999',
            'cost_price' => 'nullable|numeric|min:0|max:9999999',
            'min_stock' => 'nullable|integer|min:0|max:999999',
            'reorder_point' => 'nullable|integer|min:0|max:999999',
            'reorder_qty' => 'nullable|integer|min:0|max:999999',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'category' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:255',
            'unit_id' => 'nullable|integer|exists:units,id',
            'tax_category_id' => 'nullable|integer|exists:tax_categories,id',
            'track_batches' => 'nullable|boolean',
            'initial_quantity' => 'nullable|integer|min:0|max:999999',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ];
    }
}
