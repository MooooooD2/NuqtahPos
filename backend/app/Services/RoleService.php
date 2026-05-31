<?php

namespace App\Services;

use App\Contracts\Repositories\RoleRepositoryInterface;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RoleService
{
    private const PROTECTED_ROLES = ['admin'];

    public function __construct(private RoleRepositoryInterface $roleRepo) {}

    public function allWithPermissions(): Collection
    {
        return $this->roleRepo->allWithPermissions();
    }

    public function allPermissions(): Collection
    {
        return $this->roleRepo->allPermissions();
    }

    public function create(array $data): Role
    {
        return $this->roleRepo->create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);
    }

    public function update(Role $role, array $data): Role
    {
        $this->guardProtected($role);

        return $this->roleRepo->update($role, ['name' => $data['name']]);
    }

    public function delete(Role $role): void
    {
        $this->guardProtected($role);
        $count = $this->roleRepo->userCount($role);
        if ($count > 0) {
            throw new Exception(__('pos.role_has_users', ['count' => $count]));
        }
        $this->roleRepo->delete($role);
    }

    public function syncPermissions(Role $role, array $permissions): void
    {
        $this->guardProtected($role);
        $this->roleRepo->syncPermissions($role, $permissions);
    }

    private function guardProtected(Role $role): void
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            throw new Exception(__('pos.role_protected'));
        }
    }
}
