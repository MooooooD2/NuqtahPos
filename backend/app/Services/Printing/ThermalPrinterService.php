<?php

namespace App\Services\Printing;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Invoice;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintLog;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Services\Printing\Connectors\ConnectorFactory;
use App\Services\SettingService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ThermalPrinterService
{
    public function __construct(
        private ReceiptTemplateEngine $templateEngine,
        private SettingService $settings,
    ) {}

    // ── Public Print Methods ───────────────────────────────────────────────────

    public function printInvoice(Invoice $invoice, ?Printer $printer = null): array
    {
        $printer = $printer ?? $this->getDefaultPrinter();
        if (! $printer) {
            return $this->fallbackBrowserPrint($invoice, 'invoice');
        }

        $template = $this->settings->get('receipt_template', 'default');
        $receipt = $this->templateEngine
            ->setPaperWidth($printer->paper_width)
            ->generateSaleReceipt($invoice, $template);

        return $this->executePrint($receipt, $printer);
    }

    public function printReturnReceipt(SalesReturn $return, ?Printer $printer = null): array
    {
        $printer = $printer ?? $this->getDefaultPrinter();
        if (! $printer) {
            return $this->fallbackBrowserPrint($return, 'return');
        }

        $receipt = $this->templateEngine
            ->setPaperWidth($printer->paper_width)
            ->generateReturnReceipt($return);

        return $this->executePrint($receipt, $printer);
    }

    public function printShiftReport(CashRegisterSession $session, ?Printer $printer = null): array
    {
        $printer = $printer ?? $this->getDefaultPrinter();
        if (! $printer) {
            return $this->fallbackBrowserPrint($session, 'shift_report');
        }

        $receipt = $this->templateEngine
            ->setPaperWidth($printer->paper_width)
            ->generateShiftReport($session);

        return $this->executePrint($receipt, $printer);
    }

    public function printBarcodeLabel(Product $product, int $quantity = 1, ?Printer $printer = null): array
    {
        $printer = $printer ?? $this->getBarcodePrinter() ?? $this->getDefaultPrinter();
        if (! $printer) {
            return ['success' => false, 'message' => 'No barcode printer configured'];
        }

        $receipt = $this->templateEngine
            ->setPaperWidth($printer->paper_width)
            ->generateBarcodeLabel($product, $quantity);

        return $this->executePrint($receipt, $printer);
    }

    // ── Core Execution ─────────────────────────────────────────────────────────

    private function executePrint(array $receipt, Printer $printer): array
    {
        $printJob = PrintJob::create([
            'printer_id' => $printer->id,
            'document_type' => $receipt['document_type'],
            'document_id' => $receipt['document_id'],
            'document_number' => $receipt['document_number'],
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        try {
            $printJob->markAsProcessing();

            // Build ESC/POS binary
            $driver = new EscposDriver($receipt['chars_per_line'], $printer->character_set);
            $driver->initialize();

            foreach ($receipt['sections'] as $section) {
                $this->renderSection($driver, $section);
            }

            if ($receipt['auto_cut'] && $printer->auto_cut) {
                $driver->feed(3)->cut();
            }

            if ($receipt['open_drawer'] && $printer->auto_open_drawer) {
                $driver->openCashDrawer();
            }

            $rawData = $driver->getBuffer();
            $printJob->update(['raw_data' => base64_encode($rawData)]);

            // Send copies
            $copies = max(1, (int) ($receipt['copies'] ?? 1));
            $connector = ConnectorFactory::make($printer);
            $connector->open();
            for ($i = 0; $i < $copies; $i++) {
                $connector->send($rawData);
            }
            $connector->close();

            $printJob->markAsCompleted();
            $this->logPrint($printer, $receipt, true);

            return [
                'success' => true,
                'message' => 'Printed successfully',
                'copies' => $copies,
                'job_id' => $printJob->id,
            ];

        } catch (Exception $e) {
            $printJob->markAsFailed($e->getMessage());
            $this->logPrint($printer, $receipt, false, $e->getMessage());

            Log::error('Thermal print failed', [
                'printer' => $printer->name,
                'document' => $receipt['document_number'],
                'error' => $e->getMessage(),
            ]);

            // If permanently failed (all attempts exhausted) signal browser fallback
            if ($printJob->fresh()->status === 'failed') {
                return [
                    'success' => false,
                    'message' => 'Print failed: ' . $e->getMessage(),
                    'fallback' => 'browser',
                    'job_id' => $printJob->id,
                ];
            }

            return [
                'success' => false,
                'message' => 'Print queued for retry',
                'job_id' => $printJob->id,
            ];
        }
    }

    // ── Section Renderer ───────────────────────────────────────────────────────

    private function renderSection(EscposDriver $driver, array $section): void
    {
        if ($section['type'] === 'qr') {
            $driver->qrCode(
                $section['data'],
                $section['size'] ?? 4,
                $section['ec_level'] ?? 'M',
            );

            return;
        }

        if ($section['type'] === 'barcode') {
            $driver->barcode(
                $section['data'],
                $section['symbology'] ?? 'CODE128',
            );

            return;
        }

        foreach ($section['lines'] ?? [] as $line) {
            $format = $line['format'] ?? '';
            $text = $line['text'];

            if ($format === 'separator') {
                $driver->separator();

                continue;
            }

            if (str_starts_with($format, 'feed:')) {
                $n = (int) str_replace('feed:', '', $format);
                $driver->feed($n);

                continue;
            }

            $isBold = str_contains($format, 'bold');
            $isDouble = str_contains($format, 'double');
            $isCenter = str_contains($format, 'center');
            $isRight = str_contains($format, 'right');
            $isTwoCol = str_contains($format, 'two-column');

            if ($isBold) {
                $driver->setBold(true);
            }
            if ($isDouble) {
                $driver->setDoubleHeight(true);
            }

            if ($isTwoCol && is_array($text)) {
                $driver->twoColumnText($text['left'] ?? '', $text['right'] ?? '');
            } elseif ($isCenter) {
                $driver->centeredText(is_array($text) ? ($text['left'] ?? '') : $text);
            } elseif ($isRight) {
                $driver->rightText(is_array($text) ? ($text['right'] ?? '') : $text);
            } else {
                $driver->line(is_array($text) ? ($text['left'] ?? '') : $text);
            }

            if ($isBold) {
                $driver->setBold(false);
            }
            if ($isDouble) {
                $driver->setDoubleHeight(false);
            }
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function getDefaultPrinter(): ?Printer
    {
        $branchId = $this->getCurrentBranchId();

        return Printer::active()->forBranch($branchId)->default()->first()
            ?? Printer::active()->forBranch($branchId)->first();
    }

    private function getBarcodePrinter(): ?Printer
    {
        $id = $this->settings->get('barcode_printer_id');

        return $id ? Printer::find($id) : null;
    }

    private function getCurrentBranchId(): ?int
    {
        return session('branch_id')
            ?? Branch::where('is_default', true)->value('id');
    }

    private function fallbackBrowserPrint(mixed $document, string $type): array
    {
        return [
            'success' => true,
            'method' => 'browser',
            'message' => 'No thermal printer — using browser print',
            'document_type' => $type,
            'document_id' => $document->id,
        ];
    }

    private function logPrint(Printer $printer, array $receipt, bool $success, ?string $error = null): void
    {
        PrintLog::create([
            'printer_id' => $printer->id,
            'document_type' => $receipt['document_type'],
            'document_id' => $receipt['document_id'],
            'document_number' => $receipt['document_number'],
            'copies' => $receipt['copies'] ?? 1,
            'printed_by' => Auth::id(),
            'print_method' => 'thermal',
            'success' => $success,
            'notes' => $error,
        ]);
    }
}
