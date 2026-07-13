<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'discount_amount' => (float) $this->discount_amount,
            'subtotal' => $this->subtotal,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'unit_abbreviation' => $this->whenLoaded('product', fn () => $this->product?->unit?->abbreviation ?? $this->product?->unit?->name),
        ];
    }
}
