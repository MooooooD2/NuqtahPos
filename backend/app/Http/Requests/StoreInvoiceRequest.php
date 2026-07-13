<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('view_pos');
    }

    public function rules(): array
    {
        $maxDiscountPercent = Cache::remember(
            'setting_max_discount',
            300,
            fn () => (float) Setting::get('max_discount_percent', config('security.invoice.max_discount_percent', 20)),
        );

        return [
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount' => [
                'nullable', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    if ($value > 9999999) {
                        $fail('قيمة الخصم غير منطقية.');
                    }
                },
            ],
            'customer_id' => 'nullable|exists:customers,id',
            'cashback_amount' => 'nullable|numeric|min:0',
            // Single payment OR split payments — one must be present
            'payment_method' => 'required_without:payments|nullable|in:cash,card,transfer,wallet,credit',
            'cash_received' => 'nullable|numeric|min:0',
            // Split payments array
            'payments' => 'required_without:payment_method|nullable|array|min:1|max:10',
            'payments.*.method' => 'required|in:cash,card,transfer,wallet,credit,voucher',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'redeem_loyalty_points' => 'nullable|integer|min:1',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'branch_id' => 'nullable|exists:branches,id',
            'offline_uuid' => 'nullable|uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة منتج واحد على الأقل.',
            'items.*.product_id.exists' => 'المنتج غير موجود.',
            'items.*.quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'payment_method.in' => 'طريقة الدفع غير صالحة.',
            'cash_received.min' => 'المبلغ المستلم لا يمكن أن يكون سالباً.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge(['notes' => strip_tags((string) $this->notes)]);
        }
    }
}
