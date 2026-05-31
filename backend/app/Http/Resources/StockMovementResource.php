<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'movement_type' => $this->movement_type,
            'reason' => $this->reason,
            'reference_id' => $this->reference_id,
            'employee_name' => $this->employee_name,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
