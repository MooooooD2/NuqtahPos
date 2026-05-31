<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Spatie\Backup\BackupDestination\BackupDestination;

class BackupMonitorController extends Controller
{
    /**
     * Return backup health data for the admin dashboard.
     *
     * Reads the spatie/laravel-backup BackupDestination directly so the endpoint
     * works without running `backup:monitor` separately — useful for a live status
     * panel that polls on-demand.
     */
    public function status(): JsonResponse
    {
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $backupName = config('backup.backup.name');

        $destination = BackupDestination::create($diskName, $backupName);

        if (! $destination->isReachable()) {
            return response()->json([
                'healthy' => false,
                'status' => 'disk_unreachable',
                'disk' => $diskName,
                'message' => 'Backup disk is not reachable.',
                'newest_backup' => null,
                'oldest_backup' => null,
                'backup_count' => 0,
                'used_storage_mb' => 0,
            ], 503);
        }

        $newest = $destination->newestBackup();
        $oldest = $destination->oldestBackup();
        $all = $destination->backups();

        // A backup is considered stale if there is none or the newest is > 25 hours old.
        $maxAgeHours = 25;
        $isStale = $newest === null || $newest->date()->diffInHours(now()) > $maxAgeHours;
        $healthy = ! $isStale;

        return response()->json([
            'healthy' => $healthy,
            'status' => $healthy ? 'ok' : ($newest === null ? 'no_backups' : 'stale'),
            'disk' => $diskName,
            'backup_count' => $all->count(),
            'used_storage_mb' => round($destination->usedStorage() / 1024 / 1024, 2),
            'newest_backup' => $newest ? [
                'date' => $newest->date()->toIso8601String(),
                'age_hours' => round($newest->date()->diffInHours(now()), 1),
                'size_mb' => round($newest->sizeInBytes() / 1024 / 1024, 2),
            ] : null,
            'oldest_backup' => $oldest ? [
                'date' => $oldest->date()->toIso8601String(),
                'size_mb' => round($oldest->sizeInBytes() / 1024 / 1024, 2),
            ] : null,
            'retention' => [
                'keep_all_days' => config('backup.cleanup.default_strategy.keep_all_backups_for_days', 7),
                'keep_daily_days' => config('backup.cleanup.default_strategy.keep_daily_backups_for_days', 16),
                'max_storage_mb' => config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than', 5000),
            ],
        ]);
    }
}
