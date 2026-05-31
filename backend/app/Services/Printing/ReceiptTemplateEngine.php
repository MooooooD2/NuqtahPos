<?php

namespace App\Services\Printing;

use App\Models\CashRegisterSession;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Services\SettingService;
use Carbon\Carbon;

/**
 * Generates structured receipt data arrays from domain models.
 * Output is renderer-agnostic — works for ESC/POS, HTML, or PDF.
 *
 * Section types: header | items | totals | payment | summary | movements | footer | qr | barcode
 * Line format flags (comma-separated): center | right | bold | double | two-column | separator | feed:N
 */
class ReceiptTemplateEngine
{
    private int $charsPerLine = 48;

    public function __construct(private SettingService $settings) {}

    public function setPaperWidth(string $width): self
    {
        $this->charsPerLine = $width === '58' ? 32 : 48;

        return $this;
    }

    // ── Sale Receipt ───────────────────────────────────────────────────────────

    public function generateSaleReceipt(Invoice $invoice, string $template = 'default'): array
    {
        $invoice->load(['items.product', 'payments', 'customer', 'cashier']);

        return $this->defaultSaleReceipt($invoice);
    }

    private function defaultSaleReceipt(Invoice $invoice): array
    {
        $currency = $this->settings->get('currency_symbol', 'EGP');
        $storeName = $this->settings->get('store_name', 'POS');
        $storeAddr = $this->settings->get('store_address', '');
        $storePhone = $this->settings->get('store_phone', '');
        $footer = $this->settings->get('invoice_footer', '');

        $sections = [];

        // ── Header ──────────────────────────────────────────────────────────────
        $header = [];
        $header[] = ['text' => $storeName, 'format' => 'center,bold,double'];
        if ($storeAddr) {
            $header[] = ['text' => $storeAddr,  'format' => 'center'];
        }
        if ($storePhone) {
            $header[] = ['text' => $storePhone, 'format' => 'center'];
        }
        $header[] = ['text' => '', 'format' => 'separator'];
        $header[] = ['text' => 'TAX INVOICE',           'format' => 'center,bold'];
        $header[] = ['text' => $invoice->invoice_number, 'format' => 'center'];
        $header[] = ['text' => ($invoice->date instanceof Carbon
                        ? $invoice->date->format('Y-m-d')
                        : (string) $invoice->date),     'format' => 'center'];
        if ($invoice->customer) {
            $header[] = ['text' => 'Customer: ' . $invoice->customer->name, 'format' => 'left'];
        }
        $header[] = ['text' => 'Cashier: ' . $invoice->cashier_name, 'format' => 'left'];
        $header[] = ['text' => '', 'format' => 'separator'];
        $sections[] = ['type' => 'header', 'lines' => $header];

        // ── Items ────────────────────────────────────────────────────────────────
        $items = [];
        $items[] = ['text' => 'ITEM', 'format' => 'bold'];
        foreach ($invoice->items as $item) {
            $items[] = ['text' => $item->product_name, 'format' => 'left'];
            $items[] = [
                'text' => ['left' => "  x{$item->quantity}", 'right' => number_format($item->subtotal, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
        }
        $items[] = ['text' => '', 'format' => 'separator'];
        $sections[] = ['type' => 'items', 'lines' => $items];

        // ── Totals ───────────────────────────────────────────────────────────────
        $totals = [];
        $totals[] = [
            'text' => ['left' => 'Subtotal:', 'right' => number_format($invoice->total, 2) . " {$currency}"],
            'format' => 'two-column',
        ];
        if ($invoice->discount > 0) {
            $totals[] = [
                'text' => ['left' => 'Discount:', 'right' => '-' . number_format($invoice->discount, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
        }
        if (($invoice->loyalty_discount ?? 0) > 0) {
            $totals[] = [
                'text' => ['left' => 'Loyalty Discount:', 'right' => '-' . number_format($invoice->loyalty_discount, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
        }
        if (($invoice->tax_amount ?? 0) > 0 && $this->settings->get('show_tax_invoice', true)) {
            $totals[] = [
                'text' => ['left' => "Tax ({$invoice->tax_rate}%):", 'right' => number_format($invoice->tax_amount, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
        }
        $totals[] = ['text' => '', 'format' => 'separator'];
        $totals[] = [
            'text' => ['left' => 'TOTAL:', 'right' => number_format($invoice->final_total, 2) . " {$currency}"],
            'format' => 'two-column,bold,double',
        ];
        $sections[] = ['type' => 'totals', 'lines' => $totals];

        // ── Payment ──────────────────────────────────────────────────────────────
        $payment = [];
        if ($invoice->is_split_payment && $invoice->payments->count() > 1) {
            $payment[] = ['text' => 'PAYMENT', 'format' => 'bold'];
            foreach ($invoice->payments as $pay) {
                $payment[] = [
                    'text' => ['left' => ucfirst($pay->method) . ':', 'right' => number_format($pay->amount, 2) . " {$currency}"],
                    'format' => 'two-column',
                ];
            }
        } else {
            $payment[] = [
                'text' => ['left' => 'Payment:', 'right' => ucfirst($invoice->payment_method ?? '')],
                'format' => 'two-column',
            ];
        }
        if ($invoice->cash_received) {
            $payment[] = [
                'text' => ['left' => 'Received:', 'right' => number_format($invoice->cash_received, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
            $payment[] = [
                'text' => ['left' => 'Change:', 'right' => number_format($invoice->change_amount ?? 0, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
        }
        $sections[] = ['type' => 'payment', 'lines' => $payment];

        // ── QR Code (ETA) ────────────────────────────────────────────────────────
        if ($this->settings->get('receipt_show_qr', true)) {
            $sections[] = [
                'type' => 'qr',
                'data' => $this->buildEtaQrData($invoice),
                'size' => $this->charsPerLine >= 48 ? 4 : 2,
                'ec_level' => 'M',
            ];
        }

        // ── Footer ───────────────────────────────────────────────────────────────
        $footerLines = [];
        $footerLines[] = ['text' => '', 'format' => 'separator'];
        if ($footer) {
            $footerLines[] = ['text' => $footer, 'format' => 'center'];
        }
        $footerLines[] = ['text' => '', 'format' => 'feed:2'];
        $sections[] = ['type' => 'footer', 'lines' => $footerLines];

        return [
            'document_type' => 'invoice',
            'document_id' => $invoice->id,
            'document_number' => $invoice->invoice_number,
            'sections' => $sections,
            'chars_per_line' => $this->charsPerLine,
            'auto_cut' => true,
            'open_drawer' => ($invoice->payment_method ?? '') === 'cash',
            'copies' => (int) $this->settings->get('receipt_copies', 2),
        ];
    }

    // ── Return Receipt ─────────────────────────────────────────────────────────

    public function generateReturnReceipt(SalesReturn $return): array
    {
        $return->load(['items.product', 'invoice']);

        $currency = $this->settings->get('currency_symbol', 'EGP');
        $storeName = $this->settings->get('store_name', 'POS');
        $sections = [];

        // Header
        $header = [];
        $header[] = ['text' => $storeName,              'format' => 'center,bold,double'];
        $header[] = ['text' => '*** RETURN RECEIPT ***', 'format' => 'center,bold'];
        $header[] = ['text' => '',                       'format' => 'separator'];
        $header[] = ['text' => 'Return #: ' . $return->return_number,    'format' => 'left'];
        $header[] = ['text' => 'Original Invoice: ' . ($return->invoice_number ?? ''), 'format' => 'left'];
        $header[] = [
            'text' => 'Date: ' . ($return->return_date instanceof Carbon
                ? $return->return_date->format('Y-m-d H:i:s')
                : (string) $return->return_date),
            'format' => 'left',
        ];
        if (! empty($return->customer_name)) {
            $header[] = ['text' => 'Customer: ' . $return->customer_name, 'format' => 'left'];
        }
        if (! empty($return->processed_by_name)) {
            $header[] = ['text' => 'Processed by: ' . $return->processed_by_name, 'format' => 'left'];
        }
        $header[] = ['text' => '', 'format' => 'separator'];
        $sections[] = ['type' => 'header', 'lines' => $header];

        // Items
        $items = [];
        foreach ($return->items as $item) {
            $items[] = ['text' => $item->product_name, 'format' => 'left'];
            $items[] = [
                'text' => ['left' => "  x{$item->quantity}", 'right' => number_format($item->subtotal, 2) . " {$currency}"],
                'format' => 'two-column',
            ];
        }
        $items[] = ['text' => '', 'format' => 'separator'];
        $sections[] = ['type' => 'items', 'lines' => $items];

        // Totals
        $totals = [];
        $totals[] = [
            'text' => ['left' => 'Refund Method:', 'right' => ucfirst(str_replace('_', ' ', $return->refund_method ?? 'cash'))],
            'format' => 'two-column,bold',
        ];
        $totals[] = [
            'text' => ['left' => 'Refund Amount:', 'right' => number_format($return->refund_amount ?? 0, 2) . " {$currency}"],
            'format' => 'two-column,bold',
        ];
        if (! empty($return->reason)) {
            $totals[] = ['text' => 'Reason: ' . $return->reason, 'format' => 'left'];
        }
        $sections[] = ['type' => 'totals', 'lines' => $totals];

        // QR
        $sections[] = [
            'type' => 'qr',
            'data' => $return->return_number,
            'size' => 3,
            'ec_level' => 'L',
        ];

        // Footer
        $sections[] = [
            'type' => 'footer',
            'lines' => [
                ['text' => '',           'format' => 'separator'],
                ['text' => 'Thank you',  'format' => 'center'],
                ['text' => '',           'format' => 'feed:2'],
            ],
        ];

        return [
            'document_type' => 'return',
            'document_id' => $return->id,
            'document_number' => $return->return_number,
            'sections' => $sections,
            'chars_per_line' => $this->charsPerLine,
            'auto_cut' => true,
            'open_drawer' => ($return->refund_method ?? '') === 'cash',
            'copies' => 1,
        ];
    }

    // ── Shift Report ───────────────────────────────────────────────────────────

    public function generateShiftReport(CashRegisterSession $session): array
    {
        $session->load(['cashier', 'movements']);

        $currency = $this->settings->get('currency_symbol', 'EGP');
        $storeName = $this->settings->get('store_name', 'POS');
        $sections = [];

        // Header
        $header = [];
        $header[] = ['text' => $storeName,    'format' => 'center,bold'];
        $header[] = ['text' => 'SHIFT REPORT', 'format' => 'center,bold,double'];
        $header[] = ['text' => '',             'format' => 'separator'];
        $header[] = ['text' => 'Session: ' . ($session->session_number ?? ''),  'format' => 'left'];
        $header[] = ['text' => 'Cashier: ' . ($session->cashier_name ?? ''),    'format' => 'left'];
        $header[] = ['text' => 'Opened: ' . ($session->opened_at?->format('Y-m-d H:i') ?? ''), 'format' => 'left'];
        if ($session->closed_at) {
            $header[] = ['text' => 'Closed: ' . $session->closed_at->format('Y-m-d H:i'), 'format' => 'left'];
        }
        $header[] = ['text' => '', 'format' => 'separator'];
        $sections[] = ['type' => 'header', 'lines' => $header];

        // Financial Summary
        $summary = [];
        $summary[] = ['text' => 'FINANCIAL SUMMARY', 'format' => 'bold'];
        $summary[] = ['text' => ['left' => 'Opening Amount:', 'right' => number_format($session->opening_amount ?? 0, 2) . " {$currency}"], 'format' => 'two-column'];

        if (! is_null($session->total_sales)) {
            $summary[] = ['text' => ['left' => 'Total Sales:', 'right' => number_format($session->total_sales, 2) . " {$currency}"], 'format' => 'two-column'];
        }
        if (! is_null($session->total_card)) {
            $summary[] = ['text' => ['left' => 'Card Sales:', 'right' => number_format($session->total_card, 2) . " {$currency}"], 'format' => 'two-column'];
        }
        if (! is_null($session->total_returns) && $session->total_returns > 0) {
            $summary[] = ['text' => ['left' => 'Returns:', 'right' => '-' . number_format($session->total_returns, 2) . " {$currency}"], 'format' => 'two-column'];
        }

        $summary[] = ['text' => '', 'format' => 'separator'];

        if (! is_null($session->expected_cash)) {
            $summary[] = ['text' => ['left' => 'Expected Cash:', 'right' => number_format($session->expected_cash, 2) . " {$currency}"], 'format' => 'two-column'];
        }
        if (! is_null($session->actual_cash)) {
            $summary[] = ['text' => ['left' => 'Actual Cash:', 'right' => number_format($session->actual_cash, 2) . " {$currency}"], 'format' => 'two-column'];
        }
        if (! is_null($session->difference)) {
            $diff = $session->difference;
            $warn = abs($diff) > 5;
            $summary[] = [
                'text' => ['left' => 'Difference' . ($warn ? ' !!' : '') . ':', 'right' => ($diff >= 0 ? '+' : '') . number_format($diff, 2) . " {$currency}"],
                'format' => 'two-column' . ($warn ? ',bold' : ''),
            ];
        }
        $sections[] = ['type' => 'summary', 'lines' => $summary];

        // Movements
        if (isset($session->movements) && $session->movements->isNotEmpty()) {
            $moves = [];
            $moves[] = ['text' => '', 'format' => 'separator'];
            $moves[] = ['text' => 'MOVEMENTS', 'format' => 'bold'];
            foreach ($session->movements as $m) {
                $sign = $m->type === 'deposit' ? '+' : '-';
                $moves[] = [
                    'text' => ['left' => $m->type . ' - ' . ($m->reason ?? ''), 'right' => $sign . number_format($m->amount, 2) . " {$currency}"],
                    'format' => 'two-column',
                ];
            }
            $sections[] = ['type' => 'movements', 'lines' => $moves];
        }

        // Footer
        $sections[] = [
            'type' => 'footer',
            'lines' => [
                ['text' => '',                                          'format' => 'separator'],
                ['text' => 'Invoices: ' . ($session->invoices_count ?? 0), 'format' => 'center'],
                ['text' => '',                                          'format' => 'feed:3'],
            ],
        ];

        return [
            'document_type' => 'shift_report',
            'document_id' => $session->id,
            'document_number' => $session->session_number ?? (string) $session->id,
            'sections' => $sections,
            'chars_per_line' => $this->charsPerLine,
            'auto_cut' => true,
            'open_drawer' => false,
            'copies' => 1,
        ];
    }

    // ── Barcode Label ──────────────────────────────────────────────────────────

    public function generateBarcodeLabel(Product $product, int $quantity = 1): array
    {
        $currency = $this->settings->get('currency_symbol', 'EGP');
        $sections = [];

        $header = [];
        $header[] = ['text' => mb_substr($product->name, 0, $this->charsPerLine), 'format' => 'center,bold'];
        $header[] = ['text' => number_format($product->price, 2) . " {$currency}", 'format' => 'center'];
        $sections[] = ['type' => 'header', 'lines' => $header];

        if ($product->barcode) {
            $sections[] = [
                'type' => 'barcode',
                'data' => $product->barcode,
                'symbology' => 'CODE128',
            ];
        }

        $sections[] = [
            'type' => 'footer',
            'lines' => [['text' => '', 'format' => 'feed:1']],
        ];

        return [
            'document_type' => 'barcode_label',
            'document_id' => $product->id,
            'document_number' => $product->barcode ?? (string) $product->id,
            'sections' => $sections,
            'chars_per_line' => $this->charsPerLine,
            'auto_cut' => false,
            'open_drawer' => false,
            'copies' => $quantity,
        ];
    }

    // ── ETA QR Data ────────────────────────────────────────────────────────────

    private function buildEtaQrData(Invoice $invoice): string
    {
        $seller = $this->settings->get('store_name', '');
        $vat = $this->settings->get('tax_registration_number', '');
        $date = ($invoice->date instanceof Carbon)
            ? $invoice->date->format('Y-m-dTH:i:s')
            : (string) $invoice->date;
        $total = number_format($invoice->final_total, 2, '.', '');
        $tax = number_format($invoice->tax_amount ?? 0, 2, '.', '');

        return implode('|', [$seller, $vat, $date, $total, $tax]);
    }
}
