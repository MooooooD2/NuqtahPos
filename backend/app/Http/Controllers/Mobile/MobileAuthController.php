<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Phase 11 — Mobile API v1: Authentication
 * Used by Staff App & Customer App (Flutter).
 */
class MobileAuthController extends Controller
{
    /** POST /api/v1/auth/login */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
            'fcm_token' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => __('auth.account_inactive')], 403);
        }

        // Revoke old device token if exists
        $user->tokens()->where('name', $request->device_name)->delete();

        $token = $user->createToken($request->device_name, ['*'])->plainTextToken;

        // Register FCM token
        if ($request->fcm_token) {
            app(\App\Services\PushNotificationService::class)->registerToken(
                $user,
                $request->fcm_token,
                $request->get('device_type', 'mobile'),
                $request->get('device_id'),
            );
        }

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        // Remove FCM token
        if ($fcm = $request->input('fcm_token')) {
            app(\App\Services\PushNotificationService::class)->removeToken($request->user(), $fcm);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /** GET /api/v1/auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /** POST /api/v1/auth/push-token */
    public function updatePushToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'required|string', 'device_type' => 'nullable|string']);

        app(\App\Services\PushNotificationService::class)->registerToken(
            $request->user(),
            $request->fcm_token,
            $request->get('device_type', 'mobile'),
        );

        return response()->json(['success' => true]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'locale' => $user->locale ?? 'ar',
            'branch_id' => $user->branch_id ?? null,
            'avatar' => $user->avatar_url ?? null,
        ];
    }
}
