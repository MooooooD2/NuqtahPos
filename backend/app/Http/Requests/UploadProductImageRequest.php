<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * #33 #34 حماية رفع الصور — تقييد النوع والحجم ومنع التنفيذ
 */
class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('edit_product');
    }

    protected function prepareForValidation(): void
    {
        $file = $this->file('image');
        Log::debug('UploadProductImageRequest debug', [
            'has_file' => $file !== null,
            'content_type' => $this->header('Content-Type'),
            'original_name' => $file?->getClientOriginalName(),
            'client_mime' => $file?->getClientMimeType(),
            'real_mime' => $file?->getMimeType(),
            'size' => $file?->getSize(),
            'path' => $file?->getRealPath(),
            'is_valid' => $file?->isValid(),
            'error' => $file?->getError(),
            'getimagesize' => $file?->getRealPath() ? @getimagesize($file->getRealPath()) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                // #34 أنواع مسموحة فقط — بدون SVG أو PDF
                'mimes:jpeg,jpg,png,webp',
                // #34 حد أقصى 5MB — يسمح بصور الكاميرا/الهاتف العادية
                'max:5120',
                // #34 حد أدنى فقط — يمنع الأيقونات الصغيرة جداً بدون رفض صور الكاميرا/الهاتف العادية
                'dimensions:min_width=50,min_height=50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimes' => 'يُسمح فقط بصور JPEG, PNG, WebP.',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
            'image.dimensions' => 'أبعاد الصورة صغيرة جداً (الحد الأدنى 50×50).',
        ];
    }
}
