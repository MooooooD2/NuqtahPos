<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_roles');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_roles');
    }

    public function update(User $auth, User $target): bool
    {
        // Admin can edit anyone including themselves.
        // Other managers can edit any user except themselves.
        return $auth->hasRole('admin') || ($auth->can('manage_roles') && (int) $auth->id !== (int) $target->id);
    }

    public function delete(User $auth, User $target): bool
    {
        return $auth->can('manage_roles') && (int) $auth->id !== (int) $target->id;
    }

    public function toggleActive(User $auth, User $target): bool
    {
        return $auth->can('manage_roles') && (int) $auth->id !== (int) $target->id;
    }
}
