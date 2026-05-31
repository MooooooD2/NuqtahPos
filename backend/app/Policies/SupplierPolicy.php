<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_warehouse');
    }

    public function create(User $user): bool
    {
        return $user->can('add_supplier');
    }

    public function update(User $user, Supplier $s): bool
    {
        return $user->can('edit_supplier');
    }

    public function delete(User $user, Supplier $s): bool
    {
        return $user->can('delete_supplier');
    }
}
