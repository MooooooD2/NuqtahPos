<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create_purchase_return');
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'reason' => 'nullable|string|max:1000',
            'refund_method' => 'nullable|in:cash,credit_note',
            'items' => 'required|array|min:1|max:500',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
