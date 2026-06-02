<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HrController extends Controller
{
    use ApiResponse;

    // Helper to get display name from a User (supports both 'username' and 'full_name' columns)
    private function userName(User $u): string
    {
        return $u->full_name ?: $u->username ?: $u->email;
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $search  = $request->query('search', '');

        $query = User::query()
            ->when($search, fn ($q) => $q->where('username', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->select(['id', 'username', 'full_name', 'email', 'role', 'is_active', 'created_at']);

        $data = $query->paginate($perPage);

        $employees = $data->map(fn (User $u) => [
            'id'         => $u->id,
            'name'       => $this->userName($u),
            'email'      => $u->email,
            'position'   => $u->role,
            'department' => $u->role === 'admin' ? 'Management' : ($u->role === 'cashier' ? 'Sales' : 'Operations'),
            'salary'     => '0.00',
            'status'     => $u->is_active ? 'active' : 'inactive',
            'hire_date'  => $u->created_at?->toDateString(),
        ]);

        return $this->success([
            'data'  => $employees,
            'total' => $data->total(),
            'page'  => $data->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'nullable|email|unique:users,email',
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0',
            'status'     => 'nullable|in:active,inactive,terminated',
            'hire_date'  => 'nullable|date',
        ]);

        // Generate a unique username from the name
        $base = strtolower(str_replace(' ', '.', $data['name']));
        $username = $base;
        $i = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . $i++;
        }

        $user = User::create([
            'username'  => $username,
            'full_name' => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make('ChangeMe123!'),
            'role'      => in_array($data['position'] ?? '', ['admin', 'cashier', 'warehouse']) ? $data['position'] : 'cashier',
            'is_active' => ($data['status'] ?? 'active') === 'active',
        ]);

        return $this->success([
            'data' => [
                'id'         => $user->id,
                'name'       => $this->userName($user),
                'email'      => $user->email,
                'position'   => $data['position'] ?? null,
                'department' => $data['department'] ?? null,
                'salary'     => $data['salary'] ?? null,
                'status'     => $data['status'] ?? 'active',
                'hire_date'  => $data['hire_date'] ?? null,
            ],
        ], 'Employee created', 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'position'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0',
            'status'     => 'nullable|in:active,inactive,terminated',
            'hire_date'  => 'nullable|date',
        ]);

        $update = ['is_active' => isset($data['status']) ? $data['status'] === 'active' : $user->is_active];
        if (isset($data['name'])) $update['full_name'] = $data['name'];
        if (isset($data['email'])) $update['email'] = $data['email'];
        $user->update($update);

        return $this->success(['data' => ['id' => $user->id, 'name' => $this->userName($user)]], 'Employee updated');
    }

    public function destroy(User $user)
    {
        if ($user->id === (int) auth()->id()) {
            return $this->error('Cannot delete yourself', 422);
        }
        $user->delete();
        return $this->success([], 'Employee deleted');
    }

    public function shifts(Request $request)
    {
        if (class_exists(\App\Models\EmployeeShift::class)) {
            $shifts = \App\Models\EmployeeShift::with('user:id,username,full_name')
                ->latest()
                ->paginate(30)
                ->map(fn ($s) => [
                    'id'            => $s->id,
                    'employee_name' => $s->user ? ($s->user->full_name ?: $s->user->username) : null,
                    'start_time'    => $s->start_time,
                    'end_time'      => $s->end_time,
                    'status'        => $s->status,
                    'total_hours'   => $s->total_hours,
                ]);
            return $this->success(['data' => $shifts]);
        }
        return $this->success(['data' => []]);
    }
}
