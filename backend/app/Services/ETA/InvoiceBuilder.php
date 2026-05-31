<?php

namespace App\Services\ETA;

use App\Models\Invoice;

class InvoiceBuilder
{
    public function build(Invoice $invoice): array
    {
        return [
            'issuer' => [
                'address' => [
                    'branchID' => '0',
                    'country' => 'EG',
                    'governate' => config('eta.issuer.governate'),
                    'regionCity' => config('eta.issuer.city'),
                    'street' => config('eta.issuer.street'),
                    'buildingNumber' => config('eta.issuer.building'),
                ],
                'type' => 'B',
                'id' => config('eta.issuer.tax_number'),
                'name' => config('eta.issuer.name'),
            ],
            'receiver' => $this->buildReceiver($invoice),
            'documentType' => 'I',
            'documentTypeVersion' => '1.0',
            'dateTimeIssued' => $invoice->date->toIso8601String(),
            'taxpayerActivityCode' => config('eta.activity_code'),
            'internalID' => $invoice->invoice_number,
            'purchaseOrderReference' => null,
            'invoiceLines' => $this->buildLines($invoice),
            'totalDiscountAmount' => (float) $invoice->discount,
            'totalSalesAmount' => (float) $invoice->total,
            'netAmount' => (float) ($invoice->total - $invoice->discount),
            'taxTotals' => [
                [
                    'taxType' => 'T1',
                    'amount' => (float) $invoice->tax_amount,
                ],
            ],
            'totalAmount' => (float) $invoice->final_total,
        ];
    }

    private function buildReceiver(Invoice $invoice): array
    {
        $customer = $invoice->customer ?? null;

        if ($customer && ! empty($customer->tax_number)) {
            return [
                'type' => 'B',
                'id' => $customer->tax_number,
                'name' => $customer->name,
                'address' => [
                    'country' => 'EG',
                    'governate' => $customer->governate ?? '',
                    'regionCity' => $customer->city ?? '',
                    'street' => $customer->address ?? '',
                ],
            ];
        }

        return [
            'type' => 'P',
            'id' => $customer?->national_id ?? '',
            'name' => $invoice->customer_name ?? 'عميل نقدي',
        ];
    }

    private function buildLines(Invoice $invoice): array
    {
        return $invoice->items->map(function ($item) {
            $taxRate = (float) ($item->tax_rate ?? $item->product?->taxCategory?->rate ?? config('eta.vat_rate', 14));

            return [
                'description' => $item->product_name,
                'itemType' => $item->product->item_code_type ?? 'EGS',
                'itemCode' => $item->product->item_code ?? 'EG-' . $item->product_id,
                'unitType' => $item->product->unit_type ?? 'EA',
                'quantity' => (float) $item->quantity,
                'internalCode' => 'P-' . $item->product_id,
                'salesTotal' => (float) ($item->price * $item->quantity),
                'total' => (float) $item->subtotal,
                'valueDifference' => 0,
                'totalTaxableFees' => 0,
                'netTotal' => (float) $item->subtotal,
                'itemsDiscount' => 0,
                'unitValue' => [
                    'currencySold' => 'EGP',
                    'amountEGP' => (float) $item->price,
                ],
                'discount' => ['rate' => 0, 'amount' => 0],
                'taxableItems' => [
                    [
                        'taxType' => 'T1',
                        'amount' => (float) ($item->subtotal * ($taxRate / 100)),
                        'subType' => 'V001',
                        'rate' => $taxRate,
                    ],
                ],
            ];
        })->toArray();
    }
}
