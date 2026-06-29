<?php

namespace App\Services;

class LicenseTokenService
{
    /**
     * Sign a payload into a compact base64 token the desktop app can store
     * and read locally (expires_at, license_key, device_id) without ever
     * needing the signing secret itself.
     */
    public function sign(array $payload): string
    {
        $body = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $body, $this->secret());

        return "{$body}.{$signature}";
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = hash_hmac('sha256', $body, $this->secret());

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($body), true);

        return is_array($payload) ? $payload : null;
    }

    private function secret(): string
    {
        return config('app.license_signing_key') ?: config('app.key');
    }
}
