<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_warehouse');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('view_warehouse');
    }

    public function create(User $user): bool
    {
        return $user->can('view_warehouse');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('view_warehouse');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasRole('admin');
    }

    public function transfer(User $user): bool
    {
        return $user->can('view_warehouse');
    }
}
