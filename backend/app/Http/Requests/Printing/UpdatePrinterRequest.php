<?php

namespace App\Http\Requests\Printing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'connection_type' => ['sometimes', 'string', 'in:usb,network,windows'],
            'ip_address' => ['sometimes', 'nullable', 'ip'],
            'port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'usb_device' => ['sometimes', 'nullable', 'string', 'max:100'],
            'windows_printer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'paper_width' => ['sometimes', 'string', 'in:58,80'],
            'character_set' => ['sometimes', 'string', 'in:CP437,CP720,UTF-8'],
            'auto_cut' => ['sometimes', 'boolean'],
            'auto_open_drawer' => ['sometimes', 'boolean'],
            'copies' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
