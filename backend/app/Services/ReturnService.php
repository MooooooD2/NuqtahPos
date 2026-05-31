<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\ProductBatch;
use App\Models\ReturnItem;
use App\Models\SalesReturn;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnService
{
    public function __construct(
        private StockService $stockService,
        private ProductRepositoryInterface $productRepo,
        private CustomerService $customerService,
    ) {}

    public function processReturn(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::with('items')->lockForUpdate()->findOrFail($data['invoice_id']);

            if ($invoice->status !== 'completed') {
                throw new Exception(__('pos.invoice_not_completed'));
            }

            $returnableQtys = $this->getReturnableQuantities($invoice);

            foreach ($data['items'] as $item) {
                $max = $returnableQtys[$item['product_id']] ?? 0;
                if ($item['quantity'] <= 0 || $item['quantity'] > $max) {
                    throw new Exception(__('pos.return_quantity_exceeded', [
                        'name' => $item['product_id'],
                        'max' => $max,
                    ]));
                }
            }

            $returnNumber = SequenceService::next('return');
            $invoiceItemMap = $invoice->items->keyBy('product_id');

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $invoiceItem = $invoiceItemMap->get($item['product_id']);
                $price = $invoiceItem ? $invoiceItem->price : 0;
                $totalAmount += $price * $item['quantity'];
            }

            // Refund only what the customer actually paid (proportional to invoice discount)
            $netDiscount = ($invoice->discount ?? 0) + ($invoice->loyalty_discount ?? 0);
            $discountRatio = ($invoice->total > 0 && $netDiscount > 0)
                ? max(0.0, ($invoice->total - $netDiscount) / $invoice->total)
                : 1.0;

            $refundMethod = $data['refund_method'] ?? 'cash';
            $refundAmount = round($totalAmount * $discountRatio, 2);

            if (! \in_array($refundMethod, ['cash', 'store_credit', 'exchange'], true)) {
                throw new Exception(__('pos.invalid_refund_method'));
            }

            if ($refundMethod === 'exchange') {
                $refundAmount = 0;
            }

            $return = SalesReturn::create([
                'return_number' => $returnNumber,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_name' => $data['customer_name'] ?? null,
                'total_amount' => round($totalAmount, 2),
                'refund_method' => $refundMethod,
                'refund_amount' => $refundAmount,
                'reason' => $data['reason'] ?? null,
                'status' => 'completed',
                'return_date' => now()->toDateString(),
                'processed_by' => Auth::id(),
                'processed_by_name' => Auth::user()?->full_name ?? '',
            ]);

            foreach ($data['items'] as $item) {
                /** @var InvoiceItem|null $invoiceItem */
                $invoiceItem = $invoiceItemMap->get($item['product_id']);
                $price = $invoiceItem ? $invoiceItem->price : 0;
                $qty = $item['quantity'];
                $subtotal = round($price * $qty, 2);

                // Proportional tax refund: tax_amount / original_qty * returned_qty
                $itemTax = 0.0;
                if ($invoiceItem && $invoiceItem->quantity > 0 && $invoiceItem->tax_amount > 0) {
                    $itemTax = round($invoiceItem->tax_amount / $invoiceItem->quantity * $qty, 2);
                }

                ReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $invoiceItem?->product_name ?? '',
                    'quantity' => $qty,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                // Track returned quantity directly on the invoice item
                if ($invoiceItem) {
                    InvoiceItem::where('id', $invoiceItem->id)->increment('returned_qty', $qty);
                    InvoiceItem::where('id', $invoiceItem->id)->increment('returned_tax', $itemTax);
                }

                $product = $this->productRepo->findById($item['product_id']);
                if ($product) {
                    // FIX: restore ProductBatch.remaining_qty when the original sale tracked a batch
                    $batchId = $invoiceItem?->batch_id;
                    if ($batchId) {
                        /** @var ProductBatch|null $batch */
                        $batch = ProductBatch::lockForUpdate()->find($batchId);
                        if ($batch) {
                            $batch->increment('remaining_qty', $qty);
                            // Re-activate exhausted batch — returning stock makes it available again
                            if ($batch->status === 'exhausted') {
                                $batch->update(['status' => 'active']);
                            }
                        }
                    }

                    // FIX: pass the original cost_price so FIFO/LIFO cost layers are
                    // correctly restored.  Previously null → layers were consumed on
                    // the original sale but never recreated on return, causing the
                    // inventory valuation report to understate layer stock over time.
                    $restoreCost = ($invoiceItem && (float) $invoiceItem->cost_price > 0)
                        ? (float) $invoiceItem->cost_price
                        : null;

                    $this->stockService->addStock(
                        $product,
                        $qty,
                        __('pos.return_note', ['ret' => $returnNumber]),
                        $return->id,
                        'return',
                        $restoreCost,
                        $invoice->warehouse_id,
                        $batchId,
                    );
                }
            }

            // ── Customer financial corrections ─────────────────────────────────────
            // All customer operations run inside the same outer transaction, so they
            // are fully atomic even though each method opens its own DB::transaction()
            // (Laravel uses savepoints for nesting).

            if ($invoice->customer_id) {

                // FIX 1: store_credit — actually credit the customer's account balance.
                // Previously the SalesReturn row was saved with refund_method='store_credit'
                // but nothing touched the Customer.balance, making the credit phantom.
                if ($refundMethod === 'store_credit' && $refundAmount > 0) {
                    $customer = Customer::find($invoice->customer_id);
                    if ($customer) {
                        $this->customerService->recordStoreCredit($customer, $refundAmount, $return->id);
                    }
                }

                // FIX 2: reverse proportional credit charge if the original sale was on credit.
                // If the customer paid on credit (balance was debited), the return proportionally
                // reduces that debt — otherwise the customer owes money for goods they returned.
                if ($refundAmount > 0 && (float) $invoice->final_total > 0) {
                    $originalCredit = $invoice->is_split_payment
                        ? (float) InvoicePayment::where('invoice_id', $invoice->id)
                            ->where('method', 'credit')->sum('amount')
                        : ($invoice->payment_method === 'credit' ? (float) $invoice->final_total : 0.0);

                    if ($originalCredit > 0) {
                        $returnRatio = $refundAmount / (float) $invoice->final_total;
                        $creditToReverse = round($originalCredit * $returnRatio, 2);
                        if ($creditToReverse > 0) {
                            $customer = Customer::find($invoice->customer_id);
                            if ($customer) {
                                $this->customerService->recordPayment($customer, $creditToReverse, 'return');
                            }
                        }
                    }
                }

                // FIX 3: restore loyalty points that were REDEEMED on the original sale
                // (proportional to the return fraction of the invoice total).
                if ($invoice->loyalty_points_used > 0 && (float) $invoice->final_total > 0 && $refundAmount > 0) {
                    $returnRatio = $refundAmount / (float) $invoice->final_total;
                    $pointsToRestore = (int) floor($invoice->loyalty_points_used * $returnRatio);
                    if ($pointsToRestore > 0) {
                        Customer::lockForUpdate()
                            ->findOrFail($invoice->customer_id)
                            ->increment('loyalty_points', $pointsToRestore);
                    }
                }

                // FIX 4: deduct loyalty points that were EARNED from this sale
                // (proportional: refundAmount / earn_rate).
                // Guards against going negative — skip if customer doesn't have enough.
                if ($refundAmount > 0) {
                    $rate = (int) setting('loyalty_earn_rate', 10);
                    if ($rate > 0) {
                        $earnedOnReturn = (int) floor($refundAmount / $rate);
                        if ($earnedOnReturn > 0) {
                            $locked = Customer::lockForUpdate()->find($invoice->customer_id);
                            if ($locked && $locked->loyalty_points >= $earnedOnReturn) {
                                $locked->decrement('loyalty_points', $earnedOnReturn);
                            }
                        }
                    }
                }
            }

            Log::channel('audit')->info('return.processed', [
                'return_number' => $returnNumber,
                'invoice' => $invoice->invoice_number,
                'total' => $totalAmount,
                'refund_method' => $refundMethod,
                'refund_amount' => $refundAmount,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $return->load('items');
        });
    }

    private function getReturnableQuantities(Invoice $invoice): array
    {
        $already = ReturnItem::whereHas(
            'salesReturn',
            fn ($q) => $q->where('invoice_id', $invoice->id)->where('status', 'completed'),
        )->selectRaw('product_id, SUM(quantity) as total_returned')
            ->groupBy('product_id')
            ->pluck('total_returned', 'product_id');

        $result = [];
        foreach ($invoice->items as $item) {
            $result[$item->product_id] = max(0, $item->quantity - ($already[$item->product_id] ?? 0));
        }

        return $result;
    }
}
