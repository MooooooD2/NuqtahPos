<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function allWithRoles(): Collection;

    public function findOrFail(int $id): User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;

    public function toggleActive(User $user): User;

    public function activeAdminCount(): int;
}
