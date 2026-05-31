<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('add_stock');
    }

    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1|max:999999',
            'reason' => 'nullable|string|max:500',
            'reference_type' => 'nullable|in:purchase,adjustment,return,initial',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ];
    }
}
