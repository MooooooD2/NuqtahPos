<?php

namespace App\Policies;

use App\Models\HeldInvoice;
use App\Models\User;

class HeldInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_pos');
    }

    public function view(User $user, HeldInvoice $held): bool
    {
        return $user->can('view_pos') && $held->cashier_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('view_pos');
    }

    public function resume(User $user, HeldInvoice $held): bool
    {
        return $user->can('view_pos') && $held->cashier_id === $user->id;
    }

    public function discard(User $user, HeldInvoice $held): bool
    {
        return $user->can('view_pos') && ($held->cashier_id === $user->id || $user->hasRole('admin'));
    }
}
