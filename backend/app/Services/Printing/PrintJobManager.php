<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Services\Printing\Connectors\ConnectorFactory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrintJobManager
{
    private const BATCH_SIZE = 10;

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Process all pending (and retryable) jobs.
     * Called by the scheduled command every minute.
     *
     * @return int Number of successfully processed jobs
     */
    public function processPendingJobs(): int
    {
        $processed = 0;

        $jobs = PrintJob::retryable()
            ->with('printer')
            ->orderBy('created_at')
            ->limit(self::BATCH_SIZE)
            ->get();

        foreach ($jobs as $job) {
            if ($this->processJob($job)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Process a single print job by its ID.
     */
    public function processJobById(int $jobId): bool
    {
        $job = PrintJob::with('printer')->find($jobId);

        if (! $job) {
            Log::warning("PrintJobManager: job #{$jobId} not found");

            return false;
        }

        return $this->processJob($job);
    }

    /**
     * Cancel a pending job.
     */
    public function cancelJob(PrintJob $job): bool
    {
        if (! in_array($job->status, ['pending', 'failed'])) {
            return false;
        }

        $job->update(['status' => 'cancelled']);

        return true;
    }

    /**
     * Retry a failed job immediately (resets status to pending).
     */
    public function retryJob(PrintJob $job): bool
    {
        return $job->retry();
    }

    // ── Core Processing ────────────────────────────────────────────────────────

    private function processJob(PrintJob $job): bool
    {
        // Wrap in a transaction with row-level lock so multiple workers
        // can't pick the same job simultaneously.
        return DB::transaction(function () use ($job) {
            // Re-fetch with lock to prevent concurrent processing
            $locked = PrintJob::lockForUpdate()->find($job->id);

            if (! $locked || ! in_array($locked->status, ['pending'])) {
                return false; // Already picked up by another worker
            }

            $locked->markAsProcessing();

            try {
                $this->sendJobToPrinter($locked);
                $locked->markAsCompleted();

                Log::info("PrintJobManager: job #{$locked->id} completed", [
                    'printer' => $locked->printer->name ?? 'unknown',
                    'document' => $locked->document_number,
                ]);

                return true;

            } catch (Exception $e) {
                $locked->markAsFailed($e->getMessage());

                Log::error("PrintJobManager: job #{$locked->id} failed", [
                    'printer' => $locked->printer->name ?? 'unknown',
                    'document' => $locked->document_number,
                    'error' => $e->getMessage(),
                    'attempts' => $locked->attempts,
                ]);

                return false;
            }
        });
    }

    private function sendJobToPrinter(PrintJob $job): void
    {
        if (! $job->printer) {
            throw new Exception("Printer not found for job #{$job->id}");
        }

        if (! $job->raw_data) {
            throw new Exception("No raw data for job #{$job->id}");
        }

        $rawData = base64_decode($job->raw_data);
        $connector = ConnectorFactory::make($job->printer);

        $connector->open();

        try {
            $connector->send($rawData);
        } finally {
            $connector->close();
        }
    }

    // ── Statistics ─────────────────────────────────────────────────────────────

    /**
     * Return queue health statistics.
     */
    public function getQueueStats(): array
    {
        return [
            'pending' => PrintJob::where('status', 'pending')->count(),
            'processing' => PrintJob::where('status', 'processing')->count(),
            'failed' => PrintJob::where('status', 'failed')->count(),
            'completed_today' => PrintJob::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }

    /**
     * Unstick jobs that have been in "processing" for too long
     * (server crashed while processing).
     */
    public function releaseStuckJobs(int $minutesThreshold = 5): int
    {
        $threshold = now()->subMinutes($minutesThreshold);

        return PrintJob::where('status', 'processing')
            ->where('updated_at', '<', $threshold)
            ->update([
                'status' => 'pending',
                'error_message' => 'Released from stuck processing state',
            ]);
    }
}
