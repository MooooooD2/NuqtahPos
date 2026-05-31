<?php

namespace App\Http\Requests\Printing;

use Illuminate\Foundation\Http\FormRequest;

class PrintReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate handled at route/controller level
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'in:invoice,return,shift_report,barcode'],
            'document_id' => ['required', 'integer', 'min:1'],
            'printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'], // barcode quantity
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Document type is required.',
            'document_type.in' => 'Document type must be one of: invoice, return, shift_report, barcode.',
            'document_id.required' => 'Document ID is required.',
            'document_id.min' => 'Document ID must be a positive integer.',
            'printer_id.exists' => 'The selected printer does not exist.',
            'copies.max' => 'Maximum 10 copies allowed.',
            'quantity.max' => 'Maximum 100 barcode labels per request.',
        ];
    }
}
