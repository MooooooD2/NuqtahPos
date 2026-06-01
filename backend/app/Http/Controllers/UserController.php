<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;

class UserController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(private UserService $userService)
    {
    }

    public function all()
    {
        $this->authorize('viewAny', User::class);

        return $this->success(['data' => $this->userService->all()]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);
        $user = $this->userService->create($request->validated());
        $this->audit('user.created', User::class, (int) $user->id, ['username' => $user->username]);

        return $this->success(['user' => new UserResource($user)], '', 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);
        $updated = $this->userService->update($user, $request->validated());
        $this->audit('user.updated', User::class, (int) $updated->id);

        return $this->success(['user' => new UserResource($updated)]);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        try {
            $this->userService->delete($user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 403);
        }
        $this->audit('user.deleted', User::class, (int) $user->id, ['username' => (string) $user->username]);

        return $this->success();
    }

    public function toggleActive(User $user)
    {
        $this->authorize('toggleActive', $user);

        try {
            $updated = $this->userService->toggleActive($user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 403);
        }
        $this->audit('user.toggled', User::class, (int) $user->id, ['is_active' => $updated->is_active]);

        return $this->success(['is_active' => $updated->is_active]);
    }
}
