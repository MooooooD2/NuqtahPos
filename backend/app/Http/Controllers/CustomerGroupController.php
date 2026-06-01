<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerGroupController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $groups = CustomerGroup::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '\%_') . '%'))
            ->when(! $request->boolean('with_inactive'), fn ($q) => $q->where('is_active', true))
            ->withCount('customers')
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return $this->success($groups->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:customer_groups,name',
            'description' => 'nullable|string|max:500',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'price_level' => 'nullable|in:retail,wholesale,vip',
            'is_active' => 'boolean',
        ]);

        $group = CustomerGroup::create($data);

        return $this->success(['group' => $group], '', 201);
    }

    public function show(CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->loadCount('customers');

        return $this->success(['group' => $customerGroup]);
    }

    public function update(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100|unique:customer_groups,name,' . $customerGroup->id,
            'description' => 'nullable|string|max:500',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'price_level' => 'nullable|in:retail,wholesale,vip',
            'is_active' => 'boolean',
        ]);

        $customerGroup->update($data);

        return $this->success(['group' => $customerGroup->fresh()]);
    }

    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        if ($customerGroup->customers()->exists()) {
            return $this->error(__('pos.group_has_customers'), 422);
        }

        $customerGroup->delete();

        return $this->success([], __('pos.group_deleted'));
    }
}
