<?php

namespace App\Services\ETA;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ETAClient
{
    private string $baseUrl;

    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('eta.base_url');
        $this->clientId = config('eta.client_id');
        $this->clientSecret = config('eta.client_secret');
    }

    public function authenticate(): string
    {
        $encrypted = Cache::remember('eta_token', 3500, function () {
            $response = Http::asForm()->post(
                config('eta.identity_url') . '/connect/token',
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'InvoicingAPI',
                ],
            );

            if (! $response->successful()) {
                throw new Exception('ETA authentication failed: ' . $response->body());
            }

            return encrypt($response->json('access_token'));
        });

        return decrypt($encrypted);
    }

    public function submitDocuments(array $documents): array
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->retry(3, 1000)
            ->post("{$this->baseUrl}/api/v1.0/documentsubmissions", [
                'documents' => $documents,
            ]);

        return $response->json();
    }

    public function getDocumentStatus(string $uuid): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->retry(3, 1000)
            ->get("{$this->baseUrl}/api/v1.0/documents/{$uuid}/details")
            ->json();
    }

    public function cancelDocument(string $uuid, string $reason): bool
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->retry(3, 1000)
            ->put("{$this->baseUrl}/api/v1.0/documents/state/{$uuid}/state", [
                'status' => 'cancelled',
                'reason' => $reason,
            ]);

        return $response->successful();
    }
}
