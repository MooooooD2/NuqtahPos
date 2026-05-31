<?php

namespace App\Policies;

use App\Models\TaxCategory;
use App\Models\User;

class TaxCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_tax_categories');
    }

    public function view(User $user, TaxCategory $tc): bool
    {
        return $user->can('manage_tax_categories');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_tax_categories');
    }

    public function update(User $user, TaxCategory $tc): bool
    {
        return $user->can('manage_tax_categories');
    }

    public function delete(User $user, TaxCategory $tc): bool
    {
        return $user->hasRole('admin');
    }
}
