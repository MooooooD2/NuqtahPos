<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('edit_expense');
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|integer|exists:expense_categories,id',
            'title' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,card,transfer,wallet',
            'reference' => 'nullable|string|max:100',
            'expense_date' => 'sometimes|required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
