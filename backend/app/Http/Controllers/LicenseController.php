<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Services\LicenseTokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group Licensing
 */
class LicenseController extends Controller
{
    use ApiResponse;

    public function __construct(private LicenseTokenService $tokens)
    {
    }

    /**
     * Activate a license key for a device. First activation binds the
     * device; subsequent calls from the same device re-confirm it.
     */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'device_id' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
        ]);

        $license = $this->findByPlainKey($data['license_key']);

        if (! $license) {
            return $this->error('Invalid license key.', 404);
        }

        if ($license->status === 'revoked') {
            return $this->error('This license has been revoked.', 403);
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);
            return $this->error('This license has expired.', 403);
        }

        if ($license->device_id && $license->device_id !== $data['device_id']) {
            return $this->error('This license is already activated on another device.', 409);
        }

        $license->update([
            'device_id' => $data['device_id'],
            'device_name' => $data['device_name'] ?? $license->device_name,
            'status' => 'active',
            'activated_at' => $license->activated_at ?? now(),
            'last_validated_at' => now(),
        ]);

        return $this->success([
            'license' => $this->presentLicense($license),
            'token' => $this->tokens->sign([
                'license_id' => $license->id,
                'device_id' => $license->device_id,
                'expires_at' => $license->expires_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Periodic online re-validation (e.g. every 24h) from an already
     * activated device.
     */
    public function validateLicense(Request $request)
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'device_id' => 'required|string|max:255',
        ]);

        $license = $this->findByPlainKey($data['license_key']);

        if (! $license || $license->device_id !== $data['device_id']) {
            return $this->error('License not recognized for this device.', 404);
        }

        if ($license->status === 'revoked') {
            return $this->error('This license has been revoked.', 403);
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);
            return $this->error('This license has expired.', 403);
        }

        $license->update(['last_validated_at' => now()]);

        return $this->success([
            'license' => $this->presentLicense($license),
            'token' => $this->tokens->sign([
                'license_id' => $license->id,
                'device_id' => $license->device_id,
                'expires_at' => $license->expires_at?->toIso8601String(),
            ]),
        ]);
    }

    public function adminIndex()
    {
        $licenses = License::orderByDesc('created_at')->get();

        return $this->success(['licenses' => $licenses]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'nullable|string|max:50',
            'expires_at' => 'nullable|date',
        ]);

        $plainKey = License::generateKey();

        $license = License::create([
            'tenant_id' => $data['tenant_id'] ?? null,
            'key_prefix' => License::keyPrefix($plainKey),
            'license_key' => License::hashKey($plainKey),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => 'pending',
        ]);

        return $this->success([
            'license' => $this->presentLicense($license),
            'plain_key' => $plainKey, // shown once; not retrievable afterwards
        ], 'License created.', 201);
    }

    public function revoke(int $id)
    {
        $license = License::findOrFail($id);
        $license->update(['status' => 'revoked']);

        return $this->success(['license' => $this->presentLicense($license)]);
    }

    private function findByPlainKey(string $plainKey): ?License
    {
        $candidates = License::where('key_prefix', License::keyPrefix($plainKey))->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($plainKey, $candidate->license_key)) {
                return $candidate;
            }
        }

        return null;
    }

    private function presentLicense(License $license): array
    {
        return [
            'id' => $license->id,
            'tenant_id' => $license->tenant_id,
            'key_prefix' => $license->key_prefix,
            'device_id' => $license->device_id,
            'device_name' => $license->device_name,
            'status' => $license->status,
            'activated_at' => $license->activated_at?->toIso8601String(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'last_validated_at' => $license->last_validated_at?->toIso8601String(),
        ];
    }
}
