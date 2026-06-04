<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ProductResource;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\Printing\ThermalPrinterService;
use App\Services\SettingService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private InvoiceService $invoiceService,
        private SettingService $settingService,
        private ThermalPrinterService $printerService,
        private NotificationService $notifier,
    ) {}

    public function posPage()
    {
        $settings = $this->settingService->getPosSettings();
        $waEnabled = (bool) (config('whatsapp.enabled') && config('whatsapp.phone_number_id'));

        return view('pos.index', compact('settings', 'waEnabled'));
    }

    public function productsForCache()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('unit')
            ->get();

        return $this->success(['products' => ProductResource::collection($products)]);
    }

    public function searchProduct(Request $request)
    {
        $request->validate(['query' => 'required|string|min:1|max:100', 'exact' => 'nullable|boolean']);
        $result = $this->invoiceService->searchProduct(
            $request->string('query')->toString(),
            $request->boolean('exact'),
        );

        if ($result === null) {
            return $this->error(__('pos.product_not_found'), 404);
        }

        if ($result instanceof Product) {
            return $this->success(['single' => true, 'product' => new ProductResource($result)]);
        }

        if ($result->isEmpty()) {
            return $this->error(__('pos.product_not_found'), 404);
        }

        if ($result->count() === 1) {
            return $this->success(['single' => true, 'product' => new ProductResource($result->first())]);
        }

        return $this->success(['single' => false, 'products' => ProductResource::collection($result->values())]);
    }

    public function createInvoice(StoreInvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->createInvoice($request->validated());
            $this->audit('invoice.created', Invoice::class, $invoice->id, ['total' => $invoice->final_total]);

            // Store in-app notification for admin users
            try {
                $this->notifier->newInvoice(
                    $invoice->invoice_number,
                    (float) $invoice->final_total,
                    $invoice->payment_method ?? 'cash',
                    $invoice->customer_name,
                );
            } catch (Throwable $e) {
                Log::warning('invoice.notification_failed', [
                    'invoice' => $invoice->invoice_number,
                    'error'   => $e->getMessage(),
                ]);
            }

            $printResult = null;
            if ($this->settingService->get('print_on_sale', false)) {
                try {
                    $printResult = $this->printerService->printInvoice($invoice);
                } catch (Throwable $e) {
                    Log::warning('Auto-print failed for invoice', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                    $printResult = ['success' => false, 'fallback' => 'browser', 'message' => $e->getMessage()];
                }
            }

            return $this->success(array_filter([
                'invoice' => new InvoiceResource($invoice),
                'print_result' => $printResult,
            ]), '', 201);
        } catch (QueryException $e) {
            Log::error('invoice.create_db_error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->error(__('pos.invoice_creation_failed'), 500);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with('cashier:id,username,full_name')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $q = $request->string('search')->toString();
            $query->where(function ($q2) use ($q) {
                $q2->where('invoice_number', 'like', "%{$q}%")
                   ->orWhere('customer_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $invoices = $query->paginate($perPage);

        return $this->success([
            'data'  => $invoices->items(),
            'total' => $invoices->total(),
            'pages' => $invoices->lastPage(),
            'page'  => $invoices->currentPage(),
        ]);
    }

    public function getByNumber(Request $request)
    {
        $request->validate(['number' => 'required|string|max:50']);
        $invoice = $this->invoiceService->getByNumber($request->string('number')->toString());
        if (! $invoice) {
            return $this->error(__('pos.invoice_not_found'), 404);
        }

        return $this->success(['invoice' => new InvoiceResource($invoice)]);
    }

    public function returnableItems(Invoice $invoice)
    {
        if ($invoice->status !== 'completed') {
            return $this->error(__('pos.invoice_not_completed'), 422);
        }

        return $this->success(['items' => $this->invoiceService->getReturnableItems($invoice)]);
    }

    public function cancel(Invoice $invoice)
    {
        $this->authorize('cancel', $invoice);

        try {
            $cancelled = $this->invoiceService->cancelInvoice($invoice);
            $this->audit('invoice.cancelled', Invoice::class, $invoice->id, ['total' => $invoice->final_total]);

            return $this->success(['invoice' => new InvoiceResource($cancelled)]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * ETA submission log: paginated list of invoices with their ETA status.
     * Used for the invoice submission log / immutable archive view.
     */
    public function etaSubmissionLog(Request $request)
    {
        $this->authorize('view_accounting');

        $request->validate([
            'status' => 'nullable|in:pending,submitted,valid,invalid,cancelled,rejected',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $query = Invoice::query()
            ->select(
                'id',
                'invoice_number',
                'final_total',
                'tax_amount',
                'status',
                'eta_status',
                'eta_uuid',
                'eta_submitted_at',
                'date',
                'cashier_name',
            )
            ->orderByDesc('date');

        if ($request->filled('status')) {
            $query->where('eta_status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $perPage = (int) ($request->per_page ?? 50);

        return response()->json($query->paginate($perPage));
    }
}
