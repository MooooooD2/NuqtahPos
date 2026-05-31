<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * #33 #34 حماية رفع الصور — تقييد النوع والحجم ومنع التنفيذ
 */
class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('edit_product');
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                // #34 أنواع مسموحة فقط — بدون SVG أو PDF
                'mimes:jpeg,jpg,png,webp',
                // #34 حد أقصى 2MB
                'max:2048',
                // #34 أبعاد معقولة
                'dimensions:min_width=50,min_height=50,max_width=2000,max_height=2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimes' => 'يُسمح فقط بصور JPEG, PNG, WebP.',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
            'image.dimensions' => 'أبعاد الصورة غير مناسبة (50×50 → 2000×2000).',
        ];
    }
}
