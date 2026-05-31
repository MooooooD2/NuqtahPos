<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('add_stock');
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:1000',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.physical_count' => 'required|integer|min:0',
            'items.*.reason' => 'nullable|string|max:500',
            'items.*.auto_adjust' => 'nullable|boolean',
        ];
    }
}
