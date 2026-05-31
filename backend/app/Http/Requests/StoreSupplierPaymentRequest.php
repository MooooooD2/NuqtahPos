<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create_supplier_payment');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'payment_method' => 'required|in:cash,card,transfer,check',
            'payment_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
