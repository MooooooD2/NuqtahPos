<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->can('manage_roles');
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:50|unique:users,username|regex:/^[a-zA-Z0-9_]+$/',
            'full_name' => 'required|string|max:255',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ];
    }
}
