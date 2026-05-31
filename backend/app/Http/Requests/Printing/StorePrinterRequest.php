<?php

namespace App\Http\Requests\Printing;

use Illuminate\Foundation\Http\FormRequest;

class StorePrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'connection_type' => ['required', 'string', 'in:usb,network,windows'],
            'ip_address' => ['nullable', 'ip', 'required_if:connection_type,network'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'usb_device' => ['nullable', 'string', 'max:100', 'required_if:connection_type,usb'],
            'windows_printer_name' => ['nullable', 'string', 'max:200', 'required_if:connection_type,windows'],
            'paper_width' => ['required', 'string', 'in:58,80'],
            'character_set' => ['required', 'string', 'in:CP437,CP720,UTF-8'],
            'auto_cut' => ['boolean'],
            'auto_open_drawer' => ['boolean'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'ip_address.required_if' => 'IP address is required for network printers.',
            'ip_address.ip' => 'A valid IP address is required.',
            'usb_device.required_if' => 'USB device path is required for USB printers.',
            'windows_printer_name.required_if' => 'Windows printer name is required for Windows printers.',
        ];
    }
}
