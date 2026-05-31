<?php

use App\Models\Invoice;
use App\Models\SalesReturn;
use App\Services\WarehouseService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Schedule;

// Mark expired trials and subscriptions
Schedule::command('subscription:expire')->daily()->at('00:05');

// #48 تنبيه المخزون المنخفض يومياً
Schedule::command('stock:alert')->daily()->at('08:00');

// تنظيف الجلسات المنتهية أسبوعياً
Schedule::command('session:gc')->weekly();

// #43 نسخ احتياطي يومي للـ Audit Log
Schedule::command('audit:backup')->daily()->at('23:00');

// FIX-8: Laravel Backup (spatie/laravel-backup)
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('backup:monitor')->daily()->at('06:00');

// Expire stale product batches nightly
Schedule::call(function () {
    app(WarehouseService::class)->expireOldBatches();
})->daily()->at('00:30')->name('batches.expire');

// WhatsApp daily sales summary to manager
Schedule::call(function () {
    $service = app(WhatsAppService::class);
    $today = now()->toDateString();
    $stats = [
        'date' => $today,
        'invoice_count' => Invoice::whereDate('date', $today)->count(),
        'total_sales' => Invoice::whereDate('date', $today)->sum('final_total'),
        'return_count' => SalesReturn::whereDate('created_at', $today)->count(),
    ];
    $service->sendDailySummary($stats);
})->dailyAt('21:00')->name('whatsapp.daily_summary');
