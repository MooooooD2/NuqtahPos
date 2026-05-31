<?php

namespace App\Http\Controllers;

use App\Http\Requests\Printing\PrintReceiptRequest;
use App\Http\Requests\Printing\StorePrinterRequest;
use App\Http\Requests\Printing\UpdatePrinterRequest;
use App\Models\CashRegisterSession;
use App\Models\Invoice;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Services\Printing\PrintJobManager;
use App\Services\Printing\ThermalPrinterService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ThermalPrinterService $printerService,
        private PrintJobManager $jobManager,
    ) {}

    // ── Print Actions ──────────────────────────────────────────────────────────

    /**
     * POST /api/printing/print
     * Print any document type by routing to the appropriate method.
     */
    public function printReceipt(PrintReceiptRequest $request): JsonResponse
    {
        $type = $request->input('document_type');
        $documentId = (int) $request->input('document_id');
        $printer = $request->filled('printer_id')
            ? Printer::find($request->input('printer_id'))
            : null;

        $result = match ($type) {
            'invoice' => $this->printInvoice($documentId, $printer),
            'return' => $this->printReturn($documentId, $printer),
            'shift_report' => $this->printShiftReport($documentId, $printer),
            'barcode' => $this->printBarcode($documentId, (int) $request->input('quantity', 1), $printer),
            default => ['success' => false, 'message' => 'Unknown document type'],
        };

        if ($result['success']) {
            return $this->success(
                array_diff_key($result, ['success' => '']),
                'Print job sent successfully',
            );
        }

        return $this->error($result['message'] ?? 'Print failed', 422, $result);
    }

    /**
     * POST /api/printing/invoices/{invoice}/reprint
     */
    public function reprintInvoice(Invoice $invoice): JsonResponse
    {
        $result = $this->printerService->printInvoice($invoice);

        if ($result['success']) {
            return $this->success(array_diff_key($result, ['success' => '']), 'Reprinted successfully');
        }

        return $this->error($result['message'] ?? 'Reprint failed', 422);
    }

    // ── Printer CRUD ───────────────────────────────────────────────────────────

    /**
     * GET /api/printing/printers
     */
    public function indexPrinters(Request $request): JsonResponse
    {
        $printers = Printer::query()
            ->when($request->filled('branch_id'), fn ($q) => $q->forBranch($request->integer('branch_id')))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->with('branch:id,name')
            ->orderBy('branch_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->success(['printers' => $printers]);
    }

    /**
     * POST /api/printing/printers
     */
    public function storePrinter(StorePrinterRequest $request): JsonResponse
    {
        $printer = Printer::create($request->validated());

        return $this->success(['printer' => $printer], 'Printer created successfully', 201);
    }

    /**
     * GET /api/printing/printers/{printer}
     */
    public function showPrinter(Printer $printer): JsonResponse
    {
        return $this->success(['printer' => $printer->load('branch:id,name')]);
    }

    /**
     * PUT /api/printing/printers/{printer}
     */
    public function updatePrinter(UpdatePrinterRequest $request, Printer $printer): JsonResponse
    {
        $printer->update($request->validated());

        return $this->success(['printer' => $printer->fresh()], 'Printer updated successfully');
    }

    /**
     * DELETE /api/printing/printers/{printer}
     */
    public function destroyPrinter(Printer $printer): JsonResponse
    {
        $printer->delete();

        return $this->success([], 'Printer deleted successfully');
    }

    /**
     * POST /api/printing/printers/{printer}/test
     */
    public function testPrinter(Printer $printer): JsonResponse
    {
        try {
            $ok = $printer->testConnection();

            if ($ok) {
                return $this->success([], 'Printer is reachable');
            }

            return $this->error('Printer is not reachable', 422);

        } catch (Exception $e) {
            return $this->error('Connection test failed: ' . $e->getMessage(), 422);
        }
    }

    /**
     * POST /api/printing/printers/{printer}/set-default
     */
    public function setDefaultPrinter(Printer $printer): JsonResponse
    {
        $printer->update(['is_default' => true]); // booted() event handles clearing others

        return $this->success(['printer' => $printer->fresh()], 'Default printer updated');
    }

    // ── Print Jobs ─────────────────────────────────────────────────────────────

    /**
     * GET /api/printing/jobs
     */
    public function indexJobs(Request $request): JsonResponse
    {
        $jobs = PrintJob::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('printer_id'), fn ($q) => $q->where('printer_id', $request->integer('printer_id')))
            ->with('printer:id,name', 'createdBy:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success(['jobs' => $jobs]);
    }

    /**
     * POST /api/printing/jobs/{job}/retry
     */
    public function retryJob(PrintJob $job): JsonResponse
    {
        if (! $this->jobManager->retryJob($job)) {
            return $this->error('Job cannot be retried (max attempts reached or wrong status)', 422);
        }

        // Attempt immediate processing
        $this->jobManager->processJobById($job->id);

        return $this->success(['job' => $job->fresh()], 'Job queued for retry');
    }

    /**
     * DELETE /api/printing/jobs/{job}  — cancel a pending job
     */
    public function cancelJob(PrintJob $job): JsonResponse
    {
        if (! $this->jobManager->cancelJob($job)) {
            return $this->error('Job cannot be cancelled (not in pending/failed state)', 422);
        }

        return $this->success([], 'Job cancelled');
    }

    /**
     * GET /api/printing/queue/stats
     */
    public function queueStats(): JsonResponse
    {
        return $this->success(['stats' => $this->jobManager->getQueueStats()]);
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    private function printInvoice(int $id, ?Printer $printer): array
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return ['success' => false, 'message' => "Invoice #{$id} not found"];
        }

        return $this->printerService->printInvoice($invoice, $printer);
    }

    private function printReturn(int $id, ?Printer $printer): array
    {
        $return = SalesReturn::find($id);
        if (! $return) {
            return ['success' => false, 'message' => "Return #{$id} not found"];
        }

        return $this->printerService->printReturnReceipt($return, $printer);
    }

    private function printShiftReport(int $id, ?Printer $printer): array
    {
        $session = CashRegisterSession::find($id);
        if (! $session) {
            return ['success' => false, 'message' => "Session #{$id} not found"];
        }

        return $this->printerService->printShiftReport($session, $printer);
    }

    private function printBarcode(int $id, int $quantity, ?Printer $printer): array
    {
        $product = Product::find($id);
        if (! $product) {
            return ['success' => false, 'message' => "Product #{$id} not found"];
        }

        return $this->printerService->printBarcodeLabel($product, $quantity, $printer);
    }
}
