<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('add_supplier');
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:suppliers,phone,' . ($supplierId ?? 'NULL'),
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email:rfc|max:255',
        ];
    }
}
