<?php

namespace App\Contracts\Repositories;

use App\Models\SupplierPayment;
use Illuminate\Pagination\LengthAwarePaginator;

interface SupplierPaymentRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function create(array $data): SupplierPayment;
}
