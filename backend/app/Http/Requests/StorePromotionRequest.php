<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_promotions');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:percentage,fixed,buy_x_get_y',
            'value' => 'required_unless:type,buy_x_get_y|numeric|min:0',
            'buy_qty' => 'required_if:type,buy_x_get_y|nullable|integer|min:1',
            'get_qty' => 'required_if:type,buy_x_get_y|nullable|integer|min:1',
            'product_id' => 'nullable|integer|exists:products,id',
            'product_category' => 'nullable|string|max:100',
            'min_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ];
    }
}
