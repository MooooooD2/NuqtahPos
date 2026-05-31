<?php

namespace App\Services;

use App\Models\Branch;
use Exception;
use Illuminate\Support\Facades\DB;

class BranchService
{
    public function all(bool $activeOnly = false)
    {
        return Branch::with('manager:id,full_name')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Branch
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                Branch::where('is_default', true)->update(['is_default' => false]);
            }

            return Branch::create($data);
        });
    }

    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            if (! empty($data['is_default'])) {
                Branch::where('id', '!=', $branch->id)->update(['is_default' => false]);
            }
            $branch->update($data);

            return $branch->fresh();
        });
    }

    public function delete(Branch $branch): void
    {
        if ($branch->is_default) {
            throw new Exception(__('pos.cannot_delete_default_branch'));
        }
        if ($branch->users()->exists()) {
            throw new Exception(__('pos.branch_has_users'));
        }
        if ($branch->invoices()->exists()) {
            throw new Exception(__('pos.branch_has_invoices'));
        }
        $branch->delete();
    }

    public function defaultId(): ?int
    {
        return Branch::where('is_default', true)->value('id');
    }
}
