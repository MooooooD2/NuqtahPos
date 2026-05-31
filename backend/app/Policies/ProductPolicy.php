<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_warehouse');
    }

    public function view(User $user, Product $p): bool
    {
        return $user->can('view_warehouse');
    }

    public function create(User $user): bool
    {
        return $user->can('add_product');
    }

    public function update(User $user, Product $p): bool
    {
        return $user->can('edit_product');
    }

    public function delete(User $user, Product $p): bool
    {
        return $user->can('delete_product');
    }

    public function addStock(User $user): bool
    {
        return $user->can('add_stock');
    }
}
