<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes in-app notifications directly to the `notifications` table so they
 * appear in the header bell without needing a queue worker.
 *
 * Uses raw DB inserts to avoid:
 *  - ShouldQueue pipeline complexity with afterCommit()
 *  - Tenancy context loss inside queued jobs
 *  - Silent failures swallowed by the queue driver
 */
class NotificationService
{
    private function adminIds(): array
    {
        return DB::table('users')
            ->where('role', 'admin')
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Store a notification for every admin user directly in the DB.
     */
    private function store(string $type, array $data): void
    {
        $adminIds = $this->adminIds();

        if (empty($adminIds)) {
            Log::debug('NotificationService: no active admin users found, skipping notification.', ['type' => $type]);
            return;
        }

        $now  = now()->toDateTimeString();
        $rows = [];

        foreach ($adminIds as $userId) {
            $rows[] = [
                'id'              => (string) Str::uuid(),
                'type'            => $type,
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id'   => $userId,
                'data'            => json_encode($data),
                'read_at'         => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('notifications')->insert($rows);

        Log::debug('NotificationService: stored ' . count($rows) . ' notification(s).', [
            'type'    => $type,
            'message' => $data['message'] ?? '',
        ]);
    }

    public function lowStock(int $productId, string $productName, int $currentQty, int $minStock): void
    {
        $this->store('App\\Notifications\\LowStockNotification', [
            'message'      => "Low stock: {$productName} ({$currentQty} left, min {$minStock})",
            'subtitle'     => "Product #{$productId}",
            'product_id'   => $productId,
            'product_name' => $productName,
            'current_qty'  => $currentQty,
            'min_stock'    => $minStock,
        ]);
    }

    public function newInvoice(string $invoiceNumber, float $total, string $paymentMethod, ?string $customerName = null): void
    {
        $customer = $customerName ? " — {$customerName}" : '';
        $this->store('App\\Notifications\\NewInvoiceNotification', [
            'message'        => "New sale #{$invoiceNumber}{$customer}",
            'subtitle'       => number_format($total, 2) . ' · ' . ucfirst($paymentMethod),
            'invoice_number' => $invoiceNumber,
            'total'          => $total,
            'payment_method' => $paymentMethod,
            'customer_name'  => $customerName,
        ]);
    }

    public function leaveRequest(string $employeeName, string $leaveType, string $startDate, string $endDate, int $days): void
    {
        $this->store('App\\Notifications\\LeaveRequestNotification', [
            'message'       => "Leave request: {$employeeName}",
            'subtitle'      => ucfirst($leaveType) . " · {$days} day(s) from {$startDate}",
            'employee_name' => $employeeName,
            'leave_type'    => $leaveType,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'days'          => $days,
        ]);
    }

    public function returnProcessed(string $returnNumber, string $invoiceNumber, float $amount): void
    {
        $this->store('App\\Notifications\\ReturnProcessedNotification', [
            'message'        => "Return processed: {$returnNumber}",
            'subtitle'       => "Invoice {$invoiceNumber} · " . number_format($amount, 2),
            'return_number'  => $returnNumber,
            'invoice_number' => $invoiceNumber,
            'amount'         => $amount,
        ]);
    }

    public function expenseRecorded(string $title, float $amount, string $category = ''): void
    {
        $sub = number_format($amount, 2) . ($category ? ' · ' . $category : '');
        $this->store('App\\Notifications\\GeneralNotification', [
            'message'  => "Expense: {$title}",
            'subtitle' => $sub,
            'amount'   => $amount,
            'category' => $category,
        ]);
    }

    public function purchaseOrderCreated(string $poNumber, string $supplierName, float $total): void
    {
        $this->store('App\\Notifications\\GeneralNotification', [
            'message'       => "New PO: {$poNumber}",
            'subtitle'      => $supplierName . ' · ' . number_format($total, 2),
            'po_number'     => $poNumber,
            'supplier_name' => $supplierName,
            'total'         => $total,
        ]);
    }

    public function custom(string $message, string $subtitle = '', array $extra = []): void
    {
        $this->store('App\\Notifications\\GeneralNotification', array_merge([
            'message'  => $message,
            'subtitle' => $subtitle,
        ], $extra));
    }
}
