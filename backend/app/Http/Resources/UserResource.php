<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'created_at' => $this->created_at->toDateString(),
            // password مخفي تلقائياً من $hidden
        ];
    }
}
