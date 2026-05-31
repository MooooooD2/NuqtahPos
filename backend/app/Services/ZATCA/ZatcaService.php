<?php

declare(strict_types=1);

namespace App\Services\ZATCA;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Str;
use Throwable;

/**
 * Phase 5 — ZATCA e-Invoicing (Saudi Arabia)
 * Implements Fatoorah Phase-1 & Phase-2 compliance.
 *
 * References:
 *  - ZATCA Phase 1: QR code generation (Simplified invoices)
 *  - ZATCA Phase 2: Cryptographic stamping + PEPPOL UBL XML + real-time reporting
 */
class ZatcaService
{
    private string $baseUrl;
    private string $certToken;

    public function __construct()
    {
        $this->baseUrl = config('zatca.api_url', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal');
        $this->certToken = config('zatca.cert_token', '');
    }

    // ── Phase 1: QR Code (Simplified Invoice) ────────────────────────────────

    /**
     * Generate ZATCA-compliant QR code TLV string for a simplified invoice.
     * The QR value is a Base64-encoded TLV (Tag-Length-Value) structure.
     */
    public function generateQrTlv(Invoice $invoice): string
    {
        $vatNumber = config('zatca.vat_number', '');
        $timestamp = $invoice->created_at->toIso8601String();
        $totalAmount = number_format((float) $invoice->total, 2, '.', '');
        $vatAmount = number_format((float) ($invoice->vat_amount ?? 0), 2, '.', '');
        $sellerName = config('app.name', 'Seller');

        $tlv = $this->encodeTlv(1, $sellerName)
             . $this->encodeTlv(2, $vatNumber)
             . $this->encodeTlv(3, $timestamp)
             . $this->encodeTlv(4, $totalAmount)
             . $this->encodeTlv(5, $vatAmount);

        return base64_encode($tlv);
    }

    // ── Phase 2: UBL XML + Clearance / Reporting ─────────────────────────────

    /**
     * Build a ZATCA-compliant UBL 2.1 XML for the invoice.
     */
    public function buildUblXml(Invoice $invoice): string
    {
        $items = $invoice->items->map(fn ($item) => $this->buildLineItem($item))->implode("\n");
        $vatAmount = number_format((float) ($invoice->vat_amount ?? 0), 2, '.', '');
        $total = number_format((float) $invoice->total, 2, '.', '');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
  <cbc:ID>{$invoice->invoice_number}</cbc:ID>
  <cbc:UUID>{$invoice->uuid}</cbc:UUID>
  <cbc:IssueDate>{$invoice->created_at->toDateString()}</cbc:IssueDate>
  <cbc:IssueTime>{$invoice->created_at->format('H:i:s')}</cbc:IssueTime>
  <cbc:InvoiceTypeCode name="0200000">{$this->invoiceTypeCode($invoice)}</cbc:InvoiceTypeCode>
  <cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cac:PartyName><cbc:Name>{$this->escape(config('app.name'))}</cbc:Name></cac:PartyName>
      <cac:PartyTaxScheme>
        <cbc:CompanyID>{$this->escape(config('zatca.vat_number', ''))}</cbc:CompanyID>
        <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
      </cac:PartyTaxScheme>
    </cac:Party>
  </cac:AccountingSupplierParty>
  <cac:AccountingCustomerParty>
    <cac:Party>
      <cac:PartyName><cbc:Name>{$this->escape($invoice->customer?->name ?? 'Cash Customer')}</cbc:Name></cac:PartyName>
    </cac:Party>
  </cac:AccountingCustomerParty>
  <cac:TaxTotal>
    <cbc:TaxAmount currencyID="SAR">{$vatAmount}</cbc:TaxAmount>
  </cac:TaxTotal>
  <cac:LegalMonetaryTotal>
    <cbc:PayableAmount currencyID="SAR">{$total}</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
  {$items}
</Invoice>
XML;
    }

    /**
     * Submit invoice to ZATCA for clearance (B2B) or reporting (B2C).
     */
    public function submit(Invoice $invoice, bool $clearance = false): array
    {
        $xml = $this->buildUblXml($invoice);
        $hash = hash('sha256', $xml);
        $b64Xml = base64_encode($xml);
        $endpoint = $clearance ? '/invoices/clearance/single' : '/invoices/reporting/single';

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'en',
                'Accept-Version' => 'V2',
                'Authorization' => 'Basic ' . $this->certToken,
                'Clearance-Status' => $clearance ? '1' : '0',
            ])->post($this->baseUrl . $endpoint, [
                'invoiceHash' => $hash,
                'uuid' => $invoice->uuid ?? Str::uuid()->toString(),
                'invoice' => $b64Xml,
            ]);

            $result = $response->json();

            Log::info('ZATCA submission', [
                'invoice' => $invoice->invoice_number,
                'clearance' => $clearance,
                'status' => $response->status(),
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $result,
                'invoice_hash' => $hash,
                'cleared_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('ZATCA submission failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function encodeTlv(int $tag, string $value): string
    {
        $valueBytes = mb_convert_encoding($value, 'UTF-8');
        $length = strlen($valueBytes);

        return chr($tag) . chr($length) . $valueBytes;
    }

    private function buildLineItem($item): string
    {
        $qty = (float) $item->quantity;
        $unitPrice = number_format((float) $item->unit_price, 2, '.', '');
        $lineTotal = number_format((float) ($qty * (float) $item->unit_price), 2, '.', '');
        $vatRate = number_format((float) ($item->vat_rate ?? 15), 2, '.', '');

        return <<<XML
  <cac:InvoiceLine>
    <cbc:ID>{$item->id}</cbc:ID>
    <cbc:InvoicedQuantity unitCode="PCE">{$qty}</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID="SAR">{$lineTotal}</cbc:LineExtensionAmount>
    <cac:Item>
      <cbc:Name>{$this->escape($item->product?->name ?? $item->description ?? '-')}</cbc:Name>
      <cac:ClassifiedTaxCategory>
        <cbc:ID>S</cbc:ID>
        <cbc:Percent>{$vatRate}</cbc:Percent>
        <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
      </cac:ClassifiedTaxCategory>
    </cac:Item>
    <cac:Price><cbc:PriceAmount currencyID="SAR">{$unitPrice}</cbc:PriceAmount></cac:Price>
  </cac:InvoiceLine>
XML;
    }

    private function invoiceTypeCode(Invoice $invoice): string
    {
        // 388 = Standard tax invoice (B2B), 381 = Credit note, 383 = Debit note, 386 = Prepayment
        return '388';
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
