<?php

namespace App\Contracts\Repositories;

use App\Models\CashRegisterSession;
use Illuminate\Pagination\LengthAwarePaginator;

interface CashRegisterSessionRepositoryInterface
{
    public function currentOpen(int $cashierId): ?CashRegisterSession;

    public function findOrFail(int $id): CashRegisterSession;

    public function create(array $data): CashRegisterSession;

    public function update(CashRegisterSession $session, array $data): CashRegisterSession;

    public function history(array $filters, bool $canSeeAll, int $userId): LengthAwarePaginator;
}
