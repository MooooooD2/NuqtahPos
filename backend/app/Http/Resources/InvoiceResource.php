<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'total' => $this->total,
            'subtotal' => $this->total,
            'discount' => $this->discount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'final_total' => $this->final_total,
            'cash_received' => $this->cash_received,
            'change_amount' => $this->change_amount,
            'payment_method' => $this->payment_method,
            'cashier_name' => $this->cashier_name,
            'status' => $this->status,
            'created_at' => $this->created_at->toDateTimeString(),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'customer_phone' => $this->whenLoaded('customer', fn () => $this->customer?->phone),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            // ETA e-invoicing fields
            'eta_status' => $this->eta_status,
            'eta_uuid' => $this->eta_uuid,
            'eta_submitted_at' => $this->eta_submitted_at?->toDateTimeString(),
        ];
    }
}
