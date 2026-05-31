<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_purchase_orders');
    }

    public function create(User $user): bool
    {
        return $user->can('create_purchase_order');
    }

    public function receive(User $user, PurchaseOrder $po): bool
    {
        return $user->can('receive_purchase_order');
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $user->can('approve_purchase_order');
    }

    public function submit(User $user, PurchaseOrder $po): bool
    {
        return $user->can('create_purchase_order');
    }
}
