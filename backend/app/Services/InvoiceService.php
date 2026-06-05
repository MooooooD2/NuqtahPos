<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Jobs\SubmitInvoiceToETA;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\ProductRecipe;
use App\Models\ReturnItem;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Services\ETA\ETAClient;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceService
{
    public function __construct(
        private StockService $stockService,
        private ProductRepositoryInterface $productRepo,
        private SettingRepositoryInterface $settingRepo,
        private CustomerService $customerService,
        private TaxService $taxService,
        private RecipeService $recipeService,
        private ?CashbackService $cashbackService = null,
    ) {
        $this->cashbackService ??= app(CashbackService::class);
    }

    public function createInvoice(array $data): Invoice
    {
        // Idempotency guard for offline-created invoices.
        // If the same UUID arrives again (network retry after timeout), return the existing row.
        if (! empty($data['offline_uuid'])) {
            $existing = Invoice::where('offline_uuid', $data['offline_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        }

        /** @var Invoice $invoice */
        $invoice = DB::transaction(function () use ($data) {
            $invoiceNumber = SequenceService::next('invoice', $this->settingRepo->get('invoice_prefix', 'INV'));

            $productIds = collect($data['items'])->pluck('product_id')->unique()->toArray();
            $products = $this->productRepo->lockForUpdate($productIds);

            $allowNeg = $this->settingRepo->get('allow_negative_stock', false);
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product) {
                    throw new Exception(__('pos.product_not_found_id', ['id' => $item['product_id']]));
                }
                if (! $allowNeg && $product->quantity < $item['quantity']) {
                    throw new Exception(__('pos.insufficient_stock', ['name' => $product->name]));
                }
            }

            $taxInclusive = (bool) $this->settingRepo->get('tax_inclusive', false);

            // Resolve customer price level: customer override > group default > retail
            $priceLevel = 'retail';
            if (! empty($data['customer_id'])) {
                $customer = Customer::with('group')
                    ->find($data['customer_id']);
                if ($customer) {
                    $priceLevel = ($customer->price_level !== 'retail')
                        ? $customer->price_level
                        : ($customer->group?->price_level ?? 'retail');
                }
            }

            // Build per-line data: subtotal + resolved tax rate
            $lineData = [];
            $total = 0;
            foreach ($data['items'] as $idx => $item) {
                $product = $products->get($item['product_id']);
                $unitPrice = $product->priceFor($priceLevel);
                $subtotal = $unitPrice * $item['quantity'];
                $total += $subtotal;
                $lineData[$idx] = [
                    'product_id' => $item['product_id'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'tax_rate' => $this->taxService->resolveRate($product),
                ];
            }

            $maxDiscountPercent = (float) $this->settingRepo->get(
                'max_discount_percent',
                config('security.invoice.max_discount_percent', 20),
            );
            $requestedDiscount = (float) ($data['discount'] ?? 0);
            $maxAllowedDiscount = $total * ($maxDiscountPercent / 100);

            if ($requestedDiscount > $maxAllowedDiscount) {
                Log::channel('audit')->warning('invoice.discount_cap_exceeded', [
                    'user_id' => Auth::id(),
                    'username' => Auth::user()?->username,
                    'requested_discount' => $requestedDiscount,
                    'max_allowed' => $maxAllowedDiscount,
                    'total' => $total,
                    'ip' => request()->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                throw new Exception(__('pos.discount_exceeds_limit', ['max' => $maxDiscountPercent]));
            }

            $discount = max(0.0, min($requestedDiscount, $total, $maxAllowedDiscount));
            $afterDiscount = $total - $discount;

            // Loyalty point redemption (applied as additional discount)
            $loyaltyPointsUsed = 0;
            $loyaltyDiscount = 0.0;
            if (! empty($data['redeem_loyalty_points']) && ! empty($data['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($data['customer_id']);
                if ($customer) {
                    $pointsToRedeem = (int) $data['redeem_loyalty_points'];
                    $loyaltyDiscount = $this->customerService->redeemLoyaltyPoints($customer, $pointsToRedeem);
                    $loyaltyDiscount = min($loyaltyDiscount, $afterDiscount);
                    $loyaltyPointsUsed = $pointsToRedeem;
                    $afterDiscount -= $loyaltyDiscount;
                }
            }

            // Distribute discount proportionally and calculate per-line taxes
            $discountRatio = $total > 0 ? $afterDiscount / $total : 1;
            $taxAmount = 0.0;
            foreach ($lineData as &$line) {
                $discountedSubtotal = $line['subtotal'] * $discountRatio;
                $lineTax = $this->taxService->calculateLineTax($discountedSubtotal, $line['tax_rate'], $taxInclusive);
                $line['tax_amount'] = $lineTax['tax_amount'];
                $taxAmount += $lineTax['tax_amount'];
            }
            unset($line);
            $taxAmount = round($taxAmount, 2);

            // Blended rate stored on the invoice header for reporting/backward compat
            $blendedTaxRate = $afterDiscount > 0 ? round($taxAmount / $afterDiscount * 100, 4) : 0.0;
            $finalTotal = $afterDiscount + ($taxInclusive ? 0 : $taxAmount);

            $isSplit = ! empty($data['payments']);
            // FIX: store 'split' for split payments — was incorrectly storing the first method,
            //      which broke cancellation credit-reversal and report payment-method grouping.
            $paymentMethod = $isSplit ? 'split' : $data['payment_method'];

            $cashReceived = null;
            $changeAmount = null;

            if (! $isSplit && $paymentMethod === 'cash') {
                $cashReceived = isset($data['cash_received']) && $data['cash_received'] > 0
                    ? round((float) $data['cash_received'], 2)
                    : round($finalTotal, 2);

                if ($cashReceived < round($finalTotal, 2)) {
                    throw new Exception(__('pos.cash_received_insufficient', [
                        'total' => round($finalTotal, 2),
                        'received' => $cashReceived,
                    ]));
                }

                $changeAmount = round($cashReceived - $finalTotal, 2);
            }

            if ($isSplit) {
                $paymentsTotal = collect($data['payments'])->sum('amount');
                if (abs($paymentsTotal - round($finalTotal, 2)) > 0.01) {
                    throw new Exception(__('pos.payments_total_mismatch', [
                        'expected' => round($finalTotal, 2),
                        'received' => $paymentsTotal,
                    ]));
                }
            }

            $warehouseId = $data['warehouse_id'] ?? Warehouse::where('is_default', true)->value('id');

            if ($warehouseId) {
                // FIX: use lockForUpdate so is_locked is read atomically with the stock deductions —
                //      a plain find() would let another transaction lock the warehouse between
                //      this check and the decrement calls below.
                $warehouse = Warehouse::lockForUpdate()->find($warehouseId);
                if ($warehouse?->is_locked) {
                    throw new Exception(__('pos.warehouse_locked'));
                }
            }

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'offline_uuid' => $data['offline_uuid'] ?? null,
                'total' => round($total, 2),
                'discount' => round($discount, 2),
                'loyalty_points_used' => $loyaltyPointsUsed,
                'loyalty_discount' => round($loyaltyDiscount, 2),
                'tax_rate' => $blendedTaxRate,
                'tax_amount' => round($taxAmount, 2),
                'final_total' => round($finalTotal, 2),
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'is_split_payment' => $isSplit,
                'customer_id' => $data['customer_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? Branch::where('is_default', true)->value('id'),
                'warehouse_id' => $warehouseId,
                'cashier_id' => Auth::id(),
                'cashier_name' => Auth::user()?->full_name ?? '',
                'status' => 'completed',
                'date' => now(),
            ]);

            foreach ($data['items'] as $idx => $item) {
                $product = $products->get($item['product_id']);
                $line = $lineData[$idx];

                if ($product->track_batches) {
                    $allocations = $this->stockService->deductBatchStock(
                        $product,
                        $item['quantity'],
                        'sale',
                        __('pos.sale_deduction'),
                        $invoice->id,
                        'invoice',
                        $warehouseId,
                    );
                    // Distribute per-item tax across batch allocations by qty ratio
                    $totalAllocQty = array_sum(array_column($allocations, 'quantity'));
                    foreach ($allocations as $alloc) {
                        $qtyRatio = $totalAllocQty > 0 ? $alloc['quantity'] / $totalAllocQty : 0;
                        $allocSubtotal = round($line['unit_price'] * $alloc['quantity'], 2);
                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $alloc['quantity'],
                            'price' => $line['unit_price'],
                            'cost_price' => $product->avg_cost > 0 ? $product->avg_cost : $product->cost_price,
                            'subtotal' => $allocSubtotal,
                            'tax_rate' => $line['tax_rate'],
                            'tax_amount' => round($line['tax_amount'] * $qtyRatio, 2),
                            'warehouse_id' => $warehouseId,
                            'batch_id' => $alloc['batch_id'],
                        ]);
                    }
                } else {
                    $unitCost = $this->stockService->deductLockedStock(
                        $product,
                        $item['quantity'],
                        'sale',
                        __('pos.sale_deduction'),
                        $invoice->id,
                        'invoice',
                        $warehouseId,
                    );

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'price' => $line['unit_price'],
                        'cost_price' => $unitCost > 0 ? $unitCost : ($product->avg_cost > 0 ? $product->avg_cost : $product->cost_price),
                        'subtotal' => round($line['unit_price'] * $item['quantity'], 2),
                        'tax_rate' => $line['tax_rate'],
                        'tax_amount' => $line['tax_amount'],
                        'warehouse_id' => $warehouseId,
                    ]);
                }
            }

            if ($isSplit) {
                foreach ($data['payments'] as $payment) {
                    InvoicePayment::create([
                        'invoice_id' => $invoice->id,
                        'method' => $payment['method'],
                        'amount' => round((float) $payment['amount'], 2),
                        'reference' => $payment['reference'] ?? null,
                    ]);
                }

                $creditAmount = collect($data['payments'])
                    ->where('method', 'credit')
                    ->sum('amount');

                if ($creditAmount > 0) {
                    $this->customerService->createInvoiceCharge($invoice, (float) $creditAmount);
                }
            } else {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'method' => $paymentMethod,
                    'amount' => round($finalTotal, 2),
                ]);

                if ($paymentMethod === 'credit') {
                    $this->customerService->createInvoiceCharge($invoice);
                }
            }

            // Deduct recipe ingredients inside the transaction so a recipe failure rolls back the sale
            foreach ($data['items'] as $item) {
                $this->recipeService->deductIngredients(
                    $item['product_id'],
                    (float) $item['quantity'],
                    $invoice->id,
                    $warehouseId,
                );
            }

            // Earn loyalty points inside the transaction so they're awarded atomically
            if (! empty($data['customer_id'])) {
                $loyaltyCustomer = Customer::find($data['customer_id']);
                if ($loyaltyCustomer) {
                    $this->customerService->addLoyaltyPoints($loyaltyCustomer, $finalTotal);
                }
            }

            // Phase 8: Earn cashback after successful invoice
            try {
                $this->cashbackService->earnFromInvoice($invoice);
            } catch (Throwable $e) {
                Log::warning('cashback.earn_failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            }

            return $invoice->load(['items.product.unit', 'customer']);
        });

        if (config('eta.enabled')) {
            SubmitInvoiceToETA::dispatch($invoice->id);
        }

        try {
            app(WhatsAppService::class)->sendInvoice($invoice);
            app(WhatsAppService::class)->sendLargeInvoiceAlert($invoice);
        } catch (Throwable $e) {
            Log::warning('whatsapp.send_failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
        }

        Cache::forget('dashboard_total_revenue');
        DashboardService::forgetCache();

        return $invoice;
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        $invoice = DB::transaction(function () use ($invoice) {
            $locked = Invoice::with('items.product')->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status === 'cancelled') {
                throw new Exception(__('pos.invoice_already_cancelled'));
            }
            if ($locked->status !== 'completed') {
                throw new Exception(__('pos.invoice_cannot_be_cancelled'));
            }

            // Block cancellation if any return exists
            $hasReturns = SalesReturn::where('invoice_id', $locked->id)
                ->where('status', 'completed')
                ->exists();
            if ($hasReturns) {
                throw new Exception(__('pos.invoice_has_returns'));
            }

            // Restore stock for each item
            $warehouseId = $locked->warehouse_id ?? null;
            foreach ($locked->items as $item) {
                $product = $this->productRepo->findById($item->product_id);
                if ($product) {
                    // FIX: pass original cost_price so FIFO/LIFO cost layers are recreated
                    // on cancellation. Previously null → cost layers consumed on the original
                    // sale were never restored, causing valuation reports to understate cost
                    // layer stock over time (same root cause as the ReturnService fix).
                    $restoreCost = ((float) $item->cost_price > 0)
                        ? (float) $item->cost_price
                        : null;

                    $this->stockService->addStock(
                        $product,
                        $item->quantity,
                        __('pos.invoice_cancelled_note', ['inv' => $locked->invoice_number]),
                        $locked->id,
                        'invoice_cancel',
                        $restoreCost,
                        $warehouseId,
                        $item->batch_id,
                    );
                }
            }

            // Restore recipe ingredients consumed during this sale
            foreach ($locked->items as $item) {
                $ingredients = ProductRecipe::where('product_id', $item->product_id)
                    ->with('ingredient')
                    ->get();

                foreach ($ingredients as $line) {
                    $restoreQty = $line->quantity * $item->quantity;
                    if ($restoreQty <= 0 || ! $line->ingredient) {
                        continue;
                    }

                    $this->stockService->addStock(
                        $line->ingredient,
                        (int) ceil($restoreQty),
                        __('pos.invoice_cancelled_note', ['inv' => $locked->invoice_number]),
                        $locked->id,
                        'invoice_cancel',
                        null,
                        $warehouseId,
                    );
                }
            }

            // Reverse loyalty points that were redeemed on this invoice
            if ($locked->loyalty_points_used > 0 && $locked->customer_id) {
                $customer = Customer::lockForUpdate()->find($locked->customer_id);
                if ($customer) {
                    $customer->increment('loyalty_points', $locked->loyalty_points_used);
                }
            }

            // Reverse loyalty points that were earned from this sale
            if ($locked->customer_id) {
                $rate = (int) setting('loyalty_earn_rate', 10);
                if ($rate > 0) {
                    $earnedPoints = (int) floor((float) $locked->final_total / $rate);
                    if ($earnedPoints > 0) {
                        $customer = Customer::lockForUpdate()->find($locked->customer_id);
                        if ($customer && $customer->loyalty_points >= $earnedPoints) {
                            $customer->decrement('loyalty_points', $earnedPoints);
                        }
                    }
                }
            }

            // Reverse customer account balance if credit payment
            if ($locked->customer_id) {
                $creditAmount = $locked->is_split_payment
                    ? (float) InvoicePayment::where('invoice_id', $locked->id)
                        ->where('method', 'credit')->sum('amount')
                    : ($locked->payment_method === 'credit' ? $locked->final_total : 0.0);

                if ($creditAmount > 0) {
                    $this->customerService->recordPayment(
                        Customer::findOrFail($locked->customer_id),
                        $creditAmount,
                        'cancellation',
                    );
                }
            }

            $locked->update(['status' => 'cancelled']);

            Log::channel('audit')->info('invoice.cancelled', [
                'invoice_number' => $locked->invoice_number,
                'total' => $locked->final_total,
                'user_id' => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ]);

            Cache::forget('dashboard_total_revenue');
            DashboardService::forgetCache();

            return $locked->fresh();
        });

        // Cancel the ETA document outside the transaction (remote API call)
        if (
            config('eta.enabled') &&
            ! empty($invoice->eta_uuid) &&
            in_array($invoice->eta_status, ['submitted', 'valid'])
        ) {
            try {
                app(ETAClient::class)->cancelDocument(
                    $invoice->eta_uuid,
                    __('pos.invoice_cancelled_note', ['inv' => $invoice->invoice_number]),
                );
                $invoice->update(['eta_status' => 'cancelled']);
            } catch (Throwable $e) {
                Log::channel('audit')->error('eta.cancel_failed', [
                    'invoice_number' => $invoice->invoice_number,
                    'eta_uuid' => $invoice->eta_uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $invoice;
    }

    public function searchProduct(string $query, bool $exact = false): mixed
    {
        return $this->productRepo->search($query, $exact);
    }

    public function getByNumber(string $number): ?Invoice
    {
        return Invoice::with(['items.product.unit'])->where('invoice_number', $number)->first();
    }

    public function getReturnableItems(Invoice $invoice): array
    {
        $returned = ReturnItem::whereHas(
            'salesReturn',
            fn ($q) => $q->where('invoice_id', $invoice->id)->where('status', 'completed'),
        )->selectRaw('product_id, SUM(quantity) as total_returned')
            ->groupBy('product_id')
            ->pluck('total_returned', 'product_id');

        return $invoice->items->filter(function ($item) use ($returned) {
            return ($item->quantity - ($returned[$item->product_id] ?? 0)) > 0;
        })->map(function ($item) use ($returned) {
            $ret = $returned[$item->product_id] ?? 0;

            return [
                'id'                  => $item->id,
                'product_id'          => $item->product_id,
                'product_name'        => $item->product_name,
                'quantity'            => $item->quantity,
                'returnable_quantity' => $item->quantity - $ret,
                'unit_price'          => $item->price,
                'unit_abbreviation'   => $item->product?->unit?->abbreviation ?? $item->product?->unit?->name,
            ];
        })->values()->toArray();
    }
}
