<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 9 + Phase 11 — Push Notification Service (FCM)
 * Uses Firebase Cloud Messaging v1 (HTTP) API.
 *
 * Set in .env:
 *   FCM_PROJECT_ID=your-firebase-project
 *   FCM_SERVER_KEY=your-fcm-server-key   # Legacy, or use OAuth2 for v1
 */
class PushNotificationService
{
    private string $fcmUrl;

    public function __construct()
    {
        $projectId = config('services.fcm.project_id', '');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    }

    // ── Token Management ──────────────────────────────────────────────────────

    public function registerToken(User $user, string $token, string $deviceType = 'web', ?string $deviceId = null): void
    {
        DB::table('push_subscriptions')->updateOrInsert(
            ['user_id' => $user->id, 'fcm_token' => $token],
            [
                'device_type' => $deviceType,
                'device_id' => $deviceId,
                'last_used_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function removeToken(User $user, string $token): void
    {
        DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->where('fcm_token', $token)
            ->delete();
    }

    public function getUserTokens(User $user): Collection
    {
        return DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->pluck('fcm_token');
    }

    // ── Sending ───────────────────────────────────────────────────────────────

    /**
     * Send a push notification to a single user (all their devices).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        $tokens = $this->getUserTokens($user);

        if ($tokens->isEmpty()) {
            return false;
        }

        $anySuccess = false;

        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $anySuccess = true;
            }
        }

        return $anySuccess;
    }

    /**
     * Send to multiple users (batch).
     */
    public function sendToUsers(Collection $users, string $title, string $body, array $data = []): array
    {
        $results = ['sent' => 0, 'failed' => 0];

        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $data)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Send a push notification to a specific FCM token.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $serverKey = config('services.fcm.server_key', '');

        if (empty($serverKey) || empty($token)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => '1',
                ],
                'data' => $data,
                'priority' => 'high',
                'content_available' => true,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                // Remove invalid tokens
                if (($result['failure'] ?? 0) > 0) {
                    $errors = $result['results'] ?? [];
                    foreach ($errors as $error) {
                        if (isset($error['error']) && in_array($error['error'], ['InvalidRegistration', 'NotRegistered'], true)) {
                            DB::table('push_subscriptions')->where('fcm_token', $token)->delete();
                        }
                    }
                }

                return ($result['success'] ?? 0) > 0;
            }

            Log::warning('FCM send failed', ['status' => $response->status(), 'token' => substr($token, 0, 20)]);

            return false;
        } catch (Throwable $e) {
            Log::error('FCM exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Send notification to a topic (e.g. "branch-1", "all-staff").
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        return $this->sendToToken("/topics/{$topic}", $title, $body, $data);
    }

    // ── Built-in alert types ───────────────────────────────────────────────────

    public function lowStockAlert(User $user, string $productName, int $remaining): void
    {
        $lang = $user->locale ?? 'en';
        $title = $lang === 'ar' ? '⚠️ تنبيه مخزون منخفض' : '⚠️ Low Stock Alert';
        $body = $lang === 'ar'
            ? "المنتج: {$productName} — متبقي {$remaining} فقط"
            : "Product: {$productName} — only {$remaining} left";

        $this->sendToUser($user, $title, $body, [
            'type' => 'low_stock',
            'product' => $productName,
        ]);
    }

    public function newOrderAlert(User $user, string $invoiceNumber, float $amount): void
    {
        $lang = $user->locale ?? 'en';
        $title = $lang === 'ar' ? '🛒 فاتورة جديدة' : '🛒 New Invoice';
        $body = $lang === 'ar'
            ? "رقم الفاتورة: {$invoiceNumber} — المبلغ: " . number_format($amount, 2)
            : "Invoice #{$invoiceNumber} — Amount: " . number_format($amount, 2);

        $this->sendToUser($user, $title, $body, [
            'type' => 'new_invoice',
            'invoice' => $invoiceNumber,
        ]);
    }
}
