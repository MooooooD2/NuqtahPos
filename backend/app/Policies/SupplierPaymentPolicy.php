<?php

namespace App\Policies;

use App\Models\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_supplier_payments');
    }

    public function create(User $user): bool
    {
        return $user->can('create_supplier_payment');
    }
}
