<?php

namespace App\Http\Controllers;

use App\Services\DeviceSessionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceSessionController extends Controller
{
    use ApiResponse;
    public function __construct(private DeviceSessionService $service) {}

    /* ─── Web Page ───────────────────────────────────────────────────── */

    public function index(): \Illuminate\View\View
    {
        $sessions = $this->service->getActiveSessions(auth()->id());

        return view('device-sessions.index', compact('sessions'));
    }

    /* ─── API ────────────────────────────────────────────────────────── */

    public function list(Request $request): JsonResponse
    {
        $sessions = $this->service->getActiveSessions($request->user()->id);

        return $this->success([
            'data' => $sessions->map(fn ($s) => [
                'id'             => $s->id,
                'device_name'    => $s->device_name,
                'device_type'    => $s->device_type,
                'browser'        => $s->browser,
                'os'             => $s->os,
                'ip_address'     => $s->ip_address,
                'last_active_at' => $s->last_active_at?->diffForHumans(),
                'is_current'     => $s->is_current,
            ]),
        ]);
    }

    public function revoke(Request $request, int $id): JsonResponse
    {
        // Admin can revoke any session; other users are restricted to their own.
        $user = $request->user();
        $userId = $user->hasRole('admin') ? null : $user->id;

        $revoked = $this->service->revoke($id, $userId);

        if (! $revoked) {
            return response()->json(['message' => 'Session not found or already revoked'], 404);
        }

        return response()->json(['message' => 'Session revoked']);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $count = $this->service->revokeAll($request->user()->id);

        return response()->json(['message' => "{$count} session(s) revoked"]);
    }
}
