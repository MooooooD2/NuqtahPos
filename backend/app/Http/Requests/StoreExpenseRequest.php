<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create_expense');
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|integer|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,card,transfer,wallet',
            'reference' => 'nullable|string|max:100',
            'expense_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
