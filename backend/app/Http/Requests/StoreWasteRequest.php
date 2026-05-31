<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_warehouse');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge(['notes' => strip_tags($this->notes)]);
        }
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'batch_id' => 'nullable|integer|exists:product_batches,id',
            'quantity' => 'required|numeric|min:0.001|max:999999',
            'reason' => 'required|in:expired,damaged,theft,other',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
