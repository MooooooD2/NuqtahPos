<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:500',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,card,transfer,wallet,credit',
            'notes' => 'nullable|string|max:500',
            'expires_in_minutes' => 'nullable|integer|min:1|max:1440',
        ];
    }
}
