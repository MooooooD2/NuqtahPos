<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RoleService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(private RoleService $roleService) {}

    public function getRoles()
    {
        return $this->success(['roles' => $this->roleService->allWithPermissions()]);
    }

    public function getPermissions()
    {
        return $this->success(['permissions' => $this->roleService->allPermissions()]);
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'guard_name' => 'nullable|string|in:web,api',
        ]);
        $role = $this->roleService->create($request->only(['name', 'guard_name']));
        $this->audit('role.created', Role::class, (int) $role->id, ['name' => $role->name]);

        return $this->success(['role' => $role, 'message' => __('pos.role_created')], '', 201);
    }

    public function updateRole(Request $request, Role $role)
    {
        $request->validate(['name' => "required|string|max:100|unique:roles,name,{$role->id}"]);
        $oldName = $role->name;

        try {
            $updated = $this->roleService->update($role, $request->only('name'));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 403);
        }
        $this->audit('role.updated', Role::class, (int) $role->id, [
            'old_name' => $oldName,
            'new_name' => $updated->name,
        ]);

        return $this->success(message: __('pos.role_updated'));
    }

    public function destroyRole(Role $role)
    {
        $roleName = $role->name;

        try {
            $this->roleService->delete($role);
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'protected') ? 403 : 422;

            return $this->error($e->getMessage(), $code);
        }
        $this->audit('role.deleted', Role::class, (int) $role->id, ['name' => $roleName]);

        return $this->success(message: __('pos.role_deleted'));
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Capture before state for audit trail
        $before = $role->permissions->pluck('name')->sort()->values()->toArray();
        $after = collect($request->input('permissions', []))->sort()->values()->toArray();

        try {
            $this->roleService->syncPermissions($role, $after);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 403);
        }

        $this->audit('role.permissions_synced', Role::class, (int) $role->id, [
            'role' => $role->name,
            'removed' => array_values(array_diff($before, $after)),
            'added' => array_values(array_diff($after, $before)),
        ]);

        return $this->success(message: __('pos.permissions_updated'));
    }

    public function getUserRoles(User $user)
    {
        return $this->success([
            'roles' => $user->getRoleNames(),
            'all_roles' => $this->roleService->allWithPermissions(),
        ]);
    }

    public function assignUserRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|exists:roles,name']);

        $beforeRoles = $user->getRoleNames()->toArray();
        $newRole = $request->string('role')->toString();

        $user->syncRoles([$newRole]);

        $this->audit('user.role_assigned', User::class, (int) $user->id, [
            'username' => $user->username,
            'before' => $beforeRoles,
            'after' => [$newRole],
        ]);

        return $this->success(message: __('pos.role_assigned'));
    }
}
