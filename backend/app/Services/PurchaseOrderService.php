<?php

namespace App\Services;

use App\Contracts\Repositories\PurchaseOrderRepositoryInterface;
use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Contracts\Repositories\SupplierAccountRepositoryInterface;
use App\Contracts\Repositories\SupplierRepositoryInterface;
use App\Models\Account;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    public function __construct(
        private StockService $stockService,
        private AccountingService $accountingService,
        private SettingRepositoryInterface $settingRepo,
        private PurchaseOrderRepositoryInterface $poRepo,
        private SupplierRepositoryInterface $supplierRepo,
        private SupplierAccountRepositoryInterface $supplierAccountRepo,
    ) {}

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $poNumber = SequenceService::next('purchase');
            $discount = $data['discount'] ?? 0;

            // Compute per-item subtotals and tax (Input Tax for Net Tax Payable report)
            $totalAmount = 0.0;
            $totalTax = 0.0;
            $itemLines = [];
            foreach ($data['items'] as $item) {
                $subtotal = round((float) $item['cost_price'] * (int) $item['quantity'], 2);
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $taxAmount = round($subtotal * $taxRate / 100, 2);
                $totalAmount += $subtotal;
                $totalTax += $taxAmount;
                $itemLines[] = array_merge($item, [
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                ]);
            }

            $finalAmount = $totalAmount + $totalTax - $discount;
            $supplier = $this->supplierRepo->findOrFail($data['supplier_id']);

            $po = $this->poRepo->create([
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'supplier_name' => $supplier->name,
                'total_amount' => round($totalAmount, 2),
                'discount' => $discount,
                'tax_amount' => round($totalTax, 2),
                'final_amount' => round($finalAmount, 2),
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()?->full_name ?? '',
            ]);

            foreach ($itemLines as $item) {
                $this->poRepo->createItem([
                    'po_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'cost_price' => $item['cost_price'],
                    'selling_price' => $item['selling_price'] ?? null,
                    'subtotal' => $item['subtotal'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                ]);
            }

            return $po->load('items');
        });
    }

    public function receivePurchaseOrder(PurchaseOrder $po, array $receivedItems): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $receivedItems) {
            $receivedValue = 0.0;

            foreach ($receivedItems as $item) {
                // Lock the item row to prevent concurrent receive race conditions
                $poItem = PurchaseOrderItem::lockForUpdate()->find($item['item_id']);
                if (! $poItem) {
                    continue;
                }

                $requestedQty = $poItem->quantity;
                $alreadyRcvd = $poItem->received_quantity;
                $maxReceivable = $requestedQty - $alreadyRcvd;
                $receivedQty = (int) $item['received_quantity'];
                $rejectedQty = (int) ($item['rejected_qty'] ?? 0);
                $actualQty = max(0, $receivedQty);
                $discrepancy = $actualQty - $maxReceivable;

                if ($actualQty <= 0 && $rejectedQty <= 0) {
                    continue;
                }

                $qualityStatus = $rejectedQty > 0
                    ? ($actualQty > 0 ? 'passed' : 'rejected')
                    : 'passed';

                $this->poRepo->updateItem($poItem, [
                    'received_quantity' => $alreadyRcvd + $actualQty,
                    'rejected_qty' => $poItem->rejected_qty + $rejectedQty,
                    'quality_status' => $qualityStatus,
                    'discrepancy' => $discrepancy,
                    'discrepancy_notes' => $item['discrepancy_notes'] ?? null,
                ]);

                if ($discrepancy !== 0) {
                    Log::channel('audit')->warning('purchase_order.discrepancy', [
                        'po_number' => $po->po_number,
                        'product' => $poItem->product_name,
                        'requested' => $requestedQty,
                        'received' => $actualQty,
                        'discrepancy' => $discrepancy,
                        'user_id' => Auth::id(),
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }

                $product = $poItem->product;
                if ($product) {
                    $unitCost = isset($item['cost_price']) ? (float) $item['cost_price'] : null;

                    if ($unitCost !== null) {
                        $product->update(['cost_price' => $unitCost]);
                    }
                    if (isset($item['selling_price'])) {
                        $product->update(['price' => $item['selling_price']]);
                    }

                    $this->stockService->addStock(
                        $product,
                        $actualQty,
                        __('pos.purchase_receipt', ['po' => $po->po_number]),
                        $po->id,
                        'purchase_order',
                        $unitCost,
                    );
                }

                // Accumulate received value for supplier debt recording
                $effectiveCost = isset($item['cost_price']) ? (float) $item['cost_price'] : (float) $poItem->cost_price;
                $receivedValue += $effectiveCost * $actualQty * (1 + (float) $poItem->tax_rate / 100);
            }

            $po->refresh();
            $allReceived = $po->items->every(fn ($i) => $i->received_quantity >= $i->quantity);
            $anyReceived = $po->items->some(fn ($i) => $i->received_quantity > 0);

            $this->poRepo->update($po, [
                'status' => $allReceived ? 'received' : ($anyReceived ? 'partial' : 'pending'),
                'received_date' => $allReceived ? now() : null,
            ]);

            // Record supplier debt only when goods are actually received (not on draft creation)
            if ($receivedValue > 0.0) {
                $rounded = round($receivedValue, 2);
                $this->recordSupplierDebt($po->supplier_id, $po->id, $po->po_number, $rounded);

                // FIX: create double-entry journal for the receipt
                // DR Inventory account, CR Accounts Payable
                $this->postReceiptEntry($po, $rounded);
            }

            return $po->load('items');
        });
    }

    private function recordSupplierDebt(int $supplierId, int $poId, string $poNumber, float $amount): void
    {
        $lastEntry = $this->supplierAccountRepo->latestEntry($supplierId);
        $lastBalance = $lastEntry ? $lastEntry->balance : 0;

        $this->supplierAccountRepo->create([
            'supplier_id' => $supplierId,
            'transaction_type' => 'purchase_order',
            'reference_id' => $poId,
            'reference_number' => $poNumber,
            'debit' => $amount,
            'credit' => 0,
            'balance' => $lastBalance + $amount,
            'notes' => __('pos.po_debt_note', ['po' => $poNumber]),
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * FIX: post the double-entry journal for a goods receipt.
     *
     * DR  Inventory            (inventory_account_code setting)
     * CR  Accounts Payable     (accounts_payable_account_code setting)
     *
     * Graceful degradation: if either account code is not configured or the
     * account row does not exist, logs a warning and skips entry creation.
     */
    private function postReceiptEntry(PurchaseOrder $po, float $amount): void
    {
        $inventoryCode = $this->settingRepo->get('inventory_account_code') ?: null;
        $apCode = $this->settingRepo->get('accounts_payable_account_code') ?: null;

        if (! $inventoryCode || ! $apCode) {
            Log::warning('purchase_receipt.journal_skipped: account codes not configured', [
                'po_number' => $po->po_number,
            ]);

            return;
        }

        $inventoryAccount = Account::where('account_code', $inventoryCode)->first();
        $apAccount = Account::where('account_code', $apCode)->first();

        if (! $inventoryAccount || ! $apAccount) {
            Log::warning('purchase_receipt.journal_skipped: account not found', [
                'po_number' => $po->po_number,
                'inventory_account_code' => $inventoryCode,
                'ap_account_code' => $apCode,
                'inventory_found' => (bool) $inventoryAccount,
                'ap_found' => (bool) $apAccount,
            ]);

            return;
        }

        $desc = __('pos.purchase_receipt_journal', ['po' => $po->po_number]);
        $entry = $this->accountingService->createJournalEntry([
            'entry_date' => now()->format('Y-m-d'),
            'description' => $desc,
            'reference_type' => 'purchase_order',
            'reference_id' => $po->id,
            'lines' => [
                [
                    'account_id' => $inventoryAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $desc,
                ],
                [
                    'account_id' => $apAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $desc,
                ],
            ],
        ]);

        $this->accountingService->postEntry($entry);
    }
}
