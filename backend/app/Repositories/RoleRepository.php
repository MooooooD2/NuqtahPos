<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct()
    {
        $this->model = new Role;
    }

    public function allWithPermissions(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function allPermissions(): Collection
    {
        return Permission::all();
    }

    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(Role|Model $role, array $data): Role
    {
        $role->update($data);

        return $role->fresh();
    }

    public function delete(Role|Model $role): void
    {
        $role->delete();
    }

    public function syncPermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);
    }

    public function userCount(Role $role): int
    {
        return $role->users()->count();
    }
}
