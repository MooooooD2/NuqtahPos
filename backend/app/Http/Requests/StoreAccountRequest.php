<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage_accounts');
    }

    public function rules(): array
    {
        return [
            'account_code' => 'required|string|max:20|unique:accounts,account_code',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|integer|exists:accounts,id',
            'description' => 'nullable|string|max:500',
        ];
    }
}
