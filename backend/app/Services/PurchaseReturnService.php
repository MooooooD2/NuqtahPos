<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Models\Account;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseReturnService
{
    public function __construct(
        private StockService $stockService,
        private AccountingService $accountingService,
        private SettingRepositoryInterface $settingRepo,
        private ProductRepositoryInterface $productRepo,
        private SupplierAccountRepositoryInterface $supplierAccountRepo,
    ) {}

    public function processReturn(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::with('items.product', 'supplier')
                ->lockForUpdate()
                ->findOrFail($data['purchase_order_id']);

            if (! in_array($po->status, ['received', 'partial'])) {
                throw new Exception(__('pos.purchase_return_po_not_received'));
            }

            $returnableQtys = $this->getReturnableQuantities($po);

            foreach ($data['items'] as $item) {
                $max = $returnableQtys[$item['product_id']] ?? 0;
                if ($item['quantity'] <= 0 || $item['quantity'] > $max) {
                    throw new Exception(__('pos.return_quantity_exceeded', [
                        'name' => $item['product_id'],
                        'max' => $max,
                    ]));
                }
            }

            $returnNumber = SequenceService::next('purchase_return', 'PR');
            $poItemsByCost = $po->items->keyBy('product_id');

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $poItem = $poItemsByCost->get($item['product_id']);
                $unitCost = $poItem ? (float) $poItem->cost_price : 0;
                $totalAmount += $unitCost * $item['quantity'];
            }

            $return = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'supplier_name' => $po->supplier_name,
                'total_amount' => round($totalAmount, 2),
                'reason' => $data['reason'] ?? null,
                'refund_method' => $data['refund_method'] ?? 'credit_note',
                'status' => 'completed',
                'processed_by' => Auth::id(),
                'processed_by_name' => Auth::user()?->full_name ?? '',
                'return_date' => now()->toDateString(),
            ]);

            foreach ($data['items'] as $item) {
                $poItem = $poItemsByCost->get($item['product_id']);
                $unitCost = $poItem ? (float) $poItem->cost_price : 0;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $poItem?->product_name ?? '',
                    'quantity' => $item['quantity'],
                    'unit_cost' => $unitCost,
                    'subtotal' => round($unitCost * $item['quantity'], 2),
                ]);

                $product = $this->productRepo->findById($item['product_id']);
                if ($product) {
                    if ($product->track_batches) {
                        $this->stockService->deductBatchStock(
                            $product,
                            $item['quantity'],
                            'return_to_supplier',
                            __('pos.purchase_return_note', ['ret' => $returnNumber]),
                            $return->id,
                            'purchase_return',
                        );
                    } else {
                        $this->stockService->deductStock(
                            $product,
                            $item['quantity'],
                            'return_to_supplier',
                            __('pos.purchase_return_note', ['ret' => $returnNumber]),
                            $return->id,
                            'purchase_return',
                        );
                    }
                }
            }

            $rounded = round($totalAmount, 2);
            $this->recordSupplierCredit($po->supplier_id, $return->id, $returnNumber, $rounded);

            // FIX: create double-entry journal for the purchase return
            // DR Accounts Payable, CR Inventory
            $this->postReturnEntry($return, $rounded);

            Log::channel('audit')->info('purchase_return.processed', [
                'return_number' => $returnNumber,
                'po_number' => $po->po_number,
                'supplier' => $po->supplier_name,
                'total' => round($totalAmount, 2),
                'refund_method' => $return->refund_method,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $return->load('items');
        });
    }

    public function getReturnableQuantities(PurchaseOrder $po): array
    {
        $alreadyReturned = PurchaseReturnItem::whereHas(
            'purchaseReturn',
            fn ($q) => $q->where('purchase_order_id', $po->id)->where('status', 'completed'),
        )->selectRaw('product_id, SUM(quantity) as total_returned')
            ->groupBy('product_id')
            ->pluck('total_returned', 'product_id');

        $result = [];
        foreach ($po->items as $item) {
            $received = (int) $item->received_quantity;
            $returned = (int) ($alreadyReturned[$item->product_id] ?? 0);
            $result[$item->product_id] = max(0, $received - $returned);
        }

        return $result;
    }

    private function recordSupplierCredit(int $supplierId, int $returnId, string $returnNumber, float $amount): void
    {
        $lastEntry = $this->supplierAccountRepo->latestEntry($supplierId);
        $lastBalance = $lastEntry ? $lastEntry->balance : 0;

        $this->supplierAccountRepo->create([
            'supplier_id' => $supplierId,
            'transaction_type' => 'purchase_return',
            'reference_id' => $returnId,
            'reference_number' => $returnNumber,
            'debit' => 0,
            'credit' => $amount,
            'balance' => $lastBalance - $amount,
            'notes' => __('pos.purchase_return_credit_note', ['ret' => $returnNumber]),
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * FIX: post the double-entry journal for a purchase return.
     *
     * DR  Accounts Payable     (accounts_payable_account_code setting)
     * CR  Inventory            (inventory_account_code setting)
     *
     * Graceful degradation: if either account code is not configured or the
     * account row does not exist, logs a warning and skips entry creation.
     */
    private function postReturnEntry(PurchaseReturn $return, float $amount): void
    {
        $apCode = $this->settingRepo->get('accounts_payable_account_code') ?: null;
        $inventoryCode = $this->settingRepo->get('inventory_account_code') ?: null;

        if (! $apCode || ! $inventoryCode) {
            Log::warning('purchase_return.journal_skipped: account codes not configured', [
                'return_number' => $return->return_number,
            ]);

            return;
        }

        $apAccount = Account::where('account_code', $apCode)->first();
        $inventoryAccount = Account::where('account_code', $inventoryCode)->first();

        if (! $apAccount || ! $inventoryAccount) {
            Log::warning('purchase_return.journal_skipped: account not found', [
                'return_number' => $return->return_number,
                'ap_account_code' => $apCode,
                'inventory_account_code' => $inventoryCode,
                'ap_found' => (bool) $apAccount,
                'inventory_found' => (bool) $inventoryAccount,
            ]);

            return;
        }

        $desc = __('pos.purchase_return_journal', ['ret' => $return->return_number]);
        $entry = $this->accountingService->createJournalEntry([
            'entry_date' => now()->format('Y-m-d'),
            'description' => $desc,
            'reference_type' => 'purchase_return',
            'reference_id' => $return->id,
            'lines' => [
                [
                    'account_id' => $apAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $desc,
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $desc,
                ],
            ],
        ]);

        $this->accountingService->postEntry($entry);
    }
}
