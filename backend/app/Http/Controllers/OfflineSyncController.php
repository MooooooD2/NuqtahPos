<?php

namespace App\Http\Controllers;

use App\Services\Offline\SyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineSyncController extends Controller
{
    use ApiResponse;

    public function __construct(private SyncService $syncService) {}

    /**
     * POST /api/offline/sync
     *
     * Accepts a batch of offline-created invoices and syncs them idempotently.
     * Each invoice must include an `offline_uuid`.
     *
     * Request body:
     *   { "invoices": [ { offline_uuid, items, payment_method, ... } ] }
     *
     * Response (merged to top level via ApiResponse):
     *   { success: true, synced: N, skipped: N, failed: N, results: [...] }
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoices' => 'required|array|min:1|max:20',
            'invoices.*.offline_uuid' => 'required|uuid',
            'invoices.*.items' => 'required|array|min:1|max:100',
            'invoices.*.items.*.product_id' => 'required|integer|exists:products,id',
            'invoices.*.items.*.quantity' => 'required|integer|min:1|max:9999',
            'invoices.*.items.*.price' => 'nullable|numeric|min:0|max:9999999',
            'invoices.*.payment_method' => 'nullable|in:cash,card,transfer,wallet,credit',
            'invoices.*.discount' => 'nullable|numeric|min:0|max:9999999',
            'invoices.*.cash_received' => 'nullable|numeric|min:0',
            'invoices.*.customer_id' => 'nullable|exists:customers,id',
            'invoices.*.notes' => 'nullable|string|max:500',
        ]);

        $result = $this->syncService->syncInvoices($data['invoices']);

        return response()->json(array_merge(['success' => true], $result));
    }
}
