<?php

namespace App\Traits;

use App\Models\AuditLog as AuditLogModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

trait AuditLog
{
    protected function audit(string $action, string $model, int|string $id, array $changes = []): void
    {
        $userId = Auth::id();
        $username = Auth::user()?->username;
        $ip = $this->auditSanitizeIp(request()->ip());
        $ua = $this->auditSanitizeUserAgent(request()->userAgent());

        try {
            // Structured log file (always)
            Log::channel('audit')->info($action, [
                'model' => $model,
                'record_id' => $id,
                'user_id' => $userId,
                'username' => $username,
                'ip' => $ip,
                'user_agent' => $ua,
                'changes' => $changes,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            // Never break business operations, but record the failure so it isn't invisible
            Log::channel('stderr')->error('audit_log_file_failure', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            // Persistent DB record (queryable audit trail)
            AuditLogModel::create([
                'action' => $action,
                'model' => $model,
                'record_id' => (string) $id,
                'user_id' => $userId,
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'changes' => $changes ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Never break business operations, but record the failure so it isn't invisible
            Log::channel('stderr')->error('audit_log_db_failure', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function auditSanitizeIp(?string $ip): string
    {
        if (! $ip) {
            return 'unknown';
        }

        return \filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'invalid';
    }

    private function auditSanitizeUserAgent(?string $ua): string
    {
        if (! $ua) {
            return 'unknown';
        }

        return \substr(\preg_replace('/[\x00-\x1F\x7F]/', '', $ua), 0, 250);
    }
}
