<?php

namespace App\Http\Resources;

use App\Services\TaxService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'image' => $this->image,
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'tax_category_id' => $this->tax_category_id,
            'tax_rate' => app(TaxService::class)->resolveRate($this->resource),
            'cost_price' => $this->when(auth()->user()?->can('view_accounting'), $this->cost_price),
            'avg_cost' => $this->when(auth()->user()?->can('add_stock'), $this->avg_cost > 0 ? $this->avg_cost : $this->cost_price),
            'quantity' => $this->quantity,
            'min_stock' => $this->min_stock,
            'barcode' => $this->barcode,
            'category' => $this->category,
            'supplier' => $this->supplier,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unit?->name,
            'unit_abbreviation' => $this->unit?->abbreviation,
            'low_stock' => $this->low_stock,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
