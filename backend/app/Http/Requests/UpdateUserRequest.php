<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->can('manage_roles');
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
            'password' => ['nullable', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirm' => 'nullable|same:password',
        ];
    }
}
