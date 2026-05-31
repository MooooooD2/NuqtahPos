<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_warehouse');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('view_warehouse');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasRole('admin');
    }
}
