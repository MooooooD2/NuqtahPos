<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BranchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use ApiResponse;

    public function __construct(private BranchService $service) {}

    public function index(): JsonResponse
    {
        return $this->success(['data' => $this->service->all()]);
    }

    public function page()
    {
        return view('branches.index');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:branches,code',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'manager_id' => 'nullable|exists:users,id',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return response()->json(['success' => true, 'branch' => $this->service->create($data)], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'code' => "sometimes|string|max:20|unique:branches,code,{$branch->id}",
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'manager_id' => 'nullable|exists:users,id',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return response()->json(['success' => true, 'branch' => $this->service->update($branch, $data)]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $this->service->delete($branch);

        return response()->json(['success' => true, 'message' => __('pos.branch_deleted')]);
    }
}
