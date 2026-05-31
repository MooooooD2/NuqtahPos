<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => strip_tags($this->name)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:150',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'type' => 'nullable|in:individual,business',
            'national_id' => 'nullable|string|max:14',
            'tax_number' => 'nullable|string|max:20',
            'commercial_register' => 'nullable|string|max:30',
            'governate' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'credit_limit' => 'nullable|numeric|min:0|max:9999999',
            'notes' => 'nullable|string|max:500',
            'customer_group_id' => 'nullable|integer|exists:customer_groups,id',
            'price_level' => 'nullable|in:retail,wholesale,vip',
            'is_active' => 'boolean',
        ];
    }
}
