<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct()
    {
        $this->model = new User;
    }

    public function allWithRoles(): Collection
    {
        return User::with('roles')->get();
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User|Model $user, array $data): User
    {
        $user->update($data);

        return $user->fresh()->load('roles');
    }

    public function delete(User|Model $user): void
    {
        $user->delete();
    }

    public function toggleActive(User $user): User
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $user->fresh();
    }

    public function activeAdminCount(): int
    {
        return User::role('admin')->where('is_active', true)->count();
    }
}
