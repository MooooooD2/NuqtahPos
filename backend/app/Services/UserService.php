<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private UserRepositoryInterface $userRepo) {}

    public function all(): AnonymousResourceCollection
    {
        return UserResource::collection($this->userRepo->allWithRoles());
    }

    public function create(array $data): User
    {
        $user = $this->userRepo->create([
            'username' => $data['username'],
            'full_name' => $data['full_name'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);
        $user->syncRoles([$data['role']]);

        return $user->load('roles');
    }

    public function update(User $user, array $data): User
    {
        $updateData = collect($data)->except(['password', 'password_confirm'])->toArray();
        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }
        $updated = $this->userRepo->update($user, $updateData);
        $updated->syncRoles([$data['role']]);

        return $updated->fresh()->load('roles');
    }

    public function delete(User $user): void
    {
        if ((int) $user->id === (int) Auth::id()) {
            throw new Exception(__('pos.cannot_delete_self'));
        }
        if ($user->hasRole('admin')) {
            throw new Exception(__('pos.cannot_delete_admin'));
        }
        $this->userRepo->delete($user);
    }

    public function toggleActive(User $user): User
    {
        if ($user->hasRole('admin')) {
            throw new Exception(__('pos.cannot_deactivate_admin'));
        }

        return $this->userRepo->toggleActive($user);
    }
}
