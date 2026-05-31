<?php

namespace App\Policies;

use App\Models\User;

class SalesReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_returns');
    }

    public function create(User $user): bool
    {
        return $user->can('create_return');
    }
}
