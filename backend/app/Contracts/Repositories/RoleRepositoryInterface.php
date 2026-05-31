<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    public function allWithPermissions(): Collection;

    public function allPermissions(): Collection;

    public function findOrFail(int $id): Role;

    public function create(array $data): Role;

    public function update(Role $role, array $data): Role;

    public function delete(Role $role): void;

    public function syncPermissions(Role $role, array $permissions): void;

    public function userCount(Role $role): int;
}
