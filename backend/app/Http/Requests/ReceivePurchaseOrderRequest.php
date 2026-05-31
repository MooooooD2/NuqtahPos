<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('receive_purchase_order');
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:purchase_order_items,id',
            'items.*.received_quantity' => 'required|integer|min:0|max:99999',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
        ];
    }
}
