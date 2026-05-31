<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'type' => $this->type,
            'price_level' => $this->price_level,
            'national_id' => $this->national_id,
            'tax_number' => $this->tax_number,
            'commercial_register' => $this->commercial_register,
            'governate' => $this->governate,
            'city' => $this->city,
            'address' => $this->address,
            'credit_limit' => (float) $this->credit_limit,
            'balance' => (float) $this->balance,
            'available_credit' => (float) $this->available_credit,
            'loyalty_points' => (int) $this->loyalty_points,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'customer_group_id' => $this->customer_group_id,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
