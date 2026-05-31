<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('view_settings');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:units,name',
            'abbreviation' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ];
    }
}
