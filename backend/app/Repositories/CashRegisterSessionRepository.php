<?php

namespace App\Repositories;

use App\Contracts\Repositories\CashRegisterSessionRepositoryInterface;
use App\Models\CashRegisterSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CashRegisterSessionRepository extends BaseRepository implements CashRegisterSessionRepositoryInterface
{
    public function __construct()
    {
        $this->model = new CashRegisterSession;
    }

    public function currentOpen(int $cashierId): ?CashRegisterSession
    {
        return CashRegisterSession::where('cashier_id', $cashierId)
            ->where('status', 'open')
            ->latest()
            ->first();
    }

    public function findOrFail(int $id): CashRegisterSession
    {
        return CashRegisterSession::findOrFail($id);
    }

    public function create(array $data): CashRegisterSession
    {
        return CashRegisterSession::create($data);
    }

    public function update(CashRegisterSession|Model $session, array $data): CashRegisterSession
    {
        $session->update($data);

        return $session->fresh();
    }

    public function history(array $filters, bool $canSeeAll, int $userId): LengthAwarePaginator
    {
        $query = CashRegisterSession::with('cashier')->orderByDesc('opened_at');

        if ($canSeeAll) {
            if (! empty($filters['cashier_id'])) {
                $query->where('cashier_id', $filters['cashier_id']);
            }
        } else {
            $query->where('cashier_id', $userId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('opened_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('opened_at', '<=', $filters['date_to']);
        }

        return $query->paginate(20);
    }
}
