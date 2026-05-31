<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\ReturnItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('view_returns');
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'reason' => 'nullable|string|max:500',
            'customer_name' => 'nullable|string|max:255',
            // سيناريو 4: طريقة رد المبلغ
            'refund_method' => 'required|in:cash,store_credit,exchange',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoiceId = $this->input('invoice_id');
            if (! $invoiceId || $validator->errors()->has('invoice_id')) {
                return;
            }

            $invoice = Invoice::with('items')->find($invoiceId);
            if (! $invoice) {
                return;
            }

            // Build map of already-returned quantities
            $invoiceItemMap = $invoice->items->keyBy('product_id');
            $alreadyReturned = ReturnItem::whereHas(
                'salesReturn',
                fn ($q) => $q->where('invoice_id', $invoiceId)->where('status', 'completed'),
            )->get()->groupBy('product_id')->map(fn ($g) => $g->sum('quantity'));

            foreach ($this->input('items', []) as $idx => $item) {
                $productId = $item['product_id'] ?? null;
                if (! $productId) {
                    continue;
                }

                $invoiceItem = $invoiceItemMap->get($productId);
                if (! $invoiceItem) {
                    $validator->errors()->add("items.{$idx}.product_id", __('pos.product_not_in_invoice'));

                    continue;
                }

                $maxReturnable = $invoiceItem->quantity - ($alreadyReturned->get($productId) ?? 0);
                if ((int) ($item['quantity'] ?? 0) > $maxReturnable) {
                    $validator->errors()->add("items.{$idx}.quantity", __('pos.return_quantity_exceeded', [
                        'name' => $productId,
                        'max' => $maxReturnable,
                    ]));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'refund_method.required' => 'يجب تحديد طريقة رد المبلغ.',
            'refund_method.in' => 'طريقة رد المبلغ غير صالحة.',
        ];
    }
}
