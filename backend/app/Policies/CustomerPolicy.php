<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_customers');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('view_customers');
    }

    public function create(User $user): bool
    {
        return $user->can('add_customer');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('edit_customer');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('delete_customer');
    }
}
