<?php

namespace App\Providers;

use App\Services\Printing\PrintJobManager;
use App\Services\Printing\ReceiptTemplateEngine;
use App\Services\Printing\ThermalPrinterService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class PrintingServiceProvider extends ServiceProvider
{
    /**
     * Register all printing services as singletons.
     */
    public function register(): void
    {
        // Merge package config (allows env overrides)
        $this->mergeConfigFrom(
            base_path('config/thermal-printing.php'),
            'thermal-printing',
        );

        // ReceiptTemplateEngine — constructor-injected via container (SettingService resolved automatically)
        $this->app->singleton(ReceiptTemplateEngine::class);

        // ThermalPrinterService — let the container resolve all constructor dependencies
        $this->app->singleton(ThermalPrinterService::class);

        // PrintJobManager — no constructor dependencies
        $this->app->singleton(PrintJobManager::class);
    }

    /**
     * Boot: register scheduled job.
     */
    public function boot(): void
    {
        // Only schedule in console context to avoid HTTP request overhead
        if ($this->app->runningInConsole()) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                // Process pending print jobs every minute
                $schedule->call(function () {
                    /** @var PrintJobManager $manager */
                    $manager = app(PrintJobManager::class);

                    // Release any jobs stuck in "processing"
                    $released = $manager->releaseStuckJobs();
                    if ($released > 0) {
                        Log::info(
                            "PrintJobManager: released {$released} stuck jobs",
                        );
                    }

                    // Process pending/retryable jobs
                    $processed = $manager->processPendingJobs();
                    if ($processed > 0) {
                        Log::info(
                            "PrintJobManager: processed {$processed} print jobs",
                        );
                    }
                })
                    ->everyMinute()
                    ->name('process-print-jobs')
                    ->withoutOverlapping();
            });
        }
    }
}
