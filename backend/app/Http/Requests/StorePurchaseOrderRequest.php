<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create_purchase_order');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'order_date' => 'required|date|before_or_equal:today',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1|max:500',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:99999',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
        ];
    }
}
