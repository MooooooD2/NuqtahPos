<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\TaxCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaxCategoryController extends Controller
{
    public function all(): JsonResponse
    {
        return response()->json(TaxCategory::orderBy('rate')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => 'required|string|max:100',
            'name_en' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:tax_categories,code',
            'rate' => 'required|numeric|min:0|max:100',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['is_default'])) {
            TaxCategory::where('is_default', true)->update(['is_default' => false]);
        }

        $category = TaxCategory::create($data);

        return response()->json($category, 201);
    }

    public function update(Request $request, TaxCategory $taxCategory): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => 'sometimes|string|max:100',
            'name_en' => 'sometimes|string|max:100',
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('tax_categories', 'code')->ignore($taxCategory->id)],
            'rate' => 'sometimes|numeric|min:0|max:100',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['is_default'])) {
            TaxCategory::where('is_default', true)->where('id', '!=', $taxCategory->id)->update(['is_default' => false]);
        }

        $taxCategory->update($data);

        return response()->json($taxCategory);
    }

    public function destroy(TaxCategory $taxCategory): JsonResponse
    {
        if ($taxCategory->is_default) {
            return response()->json(['message' => __('tax.cannot_delete_default')], 422);
        }

        $taxCategory->products()->update(['tax_category_id' => null]);
        $taxCategory->delete();

        return response()->json(null, 204);
    }

    /**
     * Tax compliance report: revenue and tax collected per category within a date range.
     */
    public function report(Request $request): JsonResponse
    {
        // Accept start_date/end_date (consistent with other report endpoints) or from/to (legacy)
        $from = $request->input('start_date') ?? $request->input('from');
        $to   = $request->input('end_date')   ?? $request->input('to');

        if (! $from || ! $to) {
            return response()->json(['message' => 'start_date and end_date are required'], 422);
        }

        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.date', [$from, $to])
            ->select(
                'invoice_items.tax_rate',
                DB::raw('SUM(invoice_items.subtotal)   AS taxable_amount'),
                DB::raw('SUM(invoice_items.tax_amount) AS tax_collected'),
                DB::raw('COUNT(DISTINCT invoices.id)   AS invoice_count'),
            )
            ->groupBy('invoice_items.tax_rate')
            ->orderByDesc('tax_collected')
            ->get();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'by_rate' => $rows->map(fn ($r) => [
                'tax_rate' => (float) $r->tax_rate,
                'taxable_amount' => round((float) $r->taxable_amount, 2),
                'tax_collected' => round((float) $r->tax_collected, 2),
                'invoice_count' => (int) $r->invoice_count,
            ])->values(),
            'totals' => [
                'taxable_amount' => round($rows->sum('taxable_amount'), 2),
                'tax_collected' => round($rows->sum('tax_collected'), 2),
            ],
        ]);
    }

    /**
     * Monthly tax report: tax collected grouped by month for a given year.
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $request->validate(['year' => 'required|integer|min:2020|max:2099']);
        $year = (int) $request->year;

        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'completed')
            ->whereYear('invoices.date', $year)
            ->select(
                DB::raw('MONTH(invoices.date) AS month'),
                DB::raw('SUM(invoice_items.subtotal)   AS taxable_amount'),
                DB::raw('SUM(invoice_items.tax_amount) AS tax_collected'),
                DB::raw('SUM(invoice_items.subtotal + invoice_items.tax_amount) AS gross_revenue'),
                DB::raw('COUNT(DISTINCT invoices.id)   AS invoice_count'),
            )
            ->groupBy(DB::raw('MONTH(invoices.date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Input tax: VAT paid on received purchase orders grouped by month
        $poRows = PurchaseOrder::query()
            ->where('status', 'received')
            ->whereYear('received_date', $year)
            ->selectRaw('MONTH(received_date) AS month, SUM(tax_amount) AS input_tax')
            ->groupByRaw('MONTH(received_date)')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $rows->get($m);
            $poRow = $poRows->get($m);
            $collected = round((float) ($row->tax_collected ?? 0), 2);
            $inputTax = round((float) ($poRow->input_tax ?? 0), 2);
            $months[] = [
                'month' => $m,
                'taxable_amount' => round((float) ($row->taxable_amount ?? 0), 2),
                'tax_collected' => $collected,
                'input_tax' => $inputTax,
                'net_tax_payable' => round($collected - $inputTax, 2),
                'gross_revenue' => round((float) ($row->gross_revenue ?? 0), 2),
                'invoice_count' => (int) ($row->invoice_count ?? 0),
            ];
        }

        $totalCollected = round(collect($months)->sum('tax_collected'), 2);
        $totalInputTax = round(collect($months)->sum('input_tax'), 2);

        return response()->json([
            'year' => $year,
            'months' => $months,
            'totals' => [
                'taxable_amount' => round($rows->sum('taxable_amount'), 2),
                'tax_collected' => $totalCollected,
                'input_tax' => $totalInputTax,
                'net_tax_payable' => round($totalCollected - $totalInputTax, 2),
                'gross_revenue' => round($rows->sum('gross_revenue'), 2),
            ],
        ]);
    }
}
