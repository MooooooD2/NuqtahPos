<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Exports\ProductsTemplateExport;
use App\Imports\ProductsImport;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Handles bulk product import / export from Excel / CSV files.
 */
class ProductImportController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * GET /products/import/template
     * Download a pre-formatted Excel template with example rows.
     */
    public function template()
    {
        return Excel::download(new ProductsTemplateExport, 'products-import-template.xlsx');
    }

    /**
     * GET /products/export
     * Download products as Excel or CSV with optional filters.
     */
    public function export(Request $request)
    {
        $format = in_array($request->get('format', 'xlsx'), ['xlsx', 'csv']) ? $request->get('format', 'xlsx') : 'xlsx';
        $export = new ProductsExport(
            category: $request->get('category', ''),
            stock: $request->get('stock', ''),
            active: $request->get('active', ''),
            search: $request->get('search', ''),
        );
        $filename = 'products-' . now()->format('Y-m-d') . '.' . $format;

        return Excel::download($export, $filename);
    }

    /**
     * POST /products/import
     * Process an uploaded Excel / CSV file and upsert products.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240', // 10 MB
            ],
        ], [
            'file.required' => __('pos.import_file_required'),
            'file.mimes' => __('pos.import_file_type'),
            'file.max' => __('pos.import_file_size'),
        ]);

        try {
            $import = new ProductsImport($this->stockService);
            Excel::import($import, $request->file('file'));

            return response()->json([
                'success' => true,
                'imported' => $import->importedCount,
                'updated' => $import->updatedCount,
                'errors' => $import->errors,
                'message' => trans_choice(
                    'pos.import_result',
                    $import->importedCount + $import->updatedCount,
                    [
                        'imported' => $import->importedCount,
                        'updated' => $import->updatedCount,
                        'errors' => count($import->errors),
                    ],
                ),
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = collect($e->failures())->map(fn ($f) => [
                'row' => $f->row(),
                'error' => implode(', ', $f->errors()),
            ])->toArray();

            return response()->json([
                'success' => false,
                'errors' => $failures,
                'message' => __('pos.import_validation_failed'),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('pos.import_error') . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}
