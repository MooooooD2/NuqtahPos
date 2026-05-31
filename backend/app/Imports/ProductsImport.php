<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Product bulk-import from Excel / CSV.
 *
 * Accepted column names (both English and Arabic headings work):
 *  name / اسم_المنتج           — required
 *  barcode / الباركود
 *  category / الفئة
 *  price / السعر               — required
 *  cost_price / سعر_التكلفة
 *  wholesale_price / سعر_الجملة
 *  vip_price / سعر_VIP
 *  min_stock / الحد_الادنى
 *  initial_qty / الكمية_الابتدائية  (new products only — sets opening stock)
 *  description / الوصف
 *  is_active / نشط            (1 / 0 / yes / no / نعم / لا)
 *
 * Upsert logic:
 *  • If barcode provided → match on barcode
 *  • Else → match on exact name
 *  • Match found  → UPDATE product fields (does NOT touch stock quantity)
 *  • No match     → CREATE product; if initial_qty > 0 add opening stock
 */
class ProductsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $importedCount = 0;
    public int $updatedCount = 0;
    /** @var array<int, array{row:int, error:string}> */
    public array $errors = [];

    public function __construct(private readonly StockService $stockService) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // row 1 = heading

            try {
                $this->processRow($row->toArray(), $rowNum);
            } catch (Throwable $e) {
                $this->errors[] = ['row' => $rowNum, 'error' => $e->getMessage()];
            }
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function processRow(array $row, int $rowNum): void
    {
        // ── Normalise keys (strip BOM, lowercase, trim) ───────────────────────
        $r = [];
        foreach ($row as $k => $v) {
            $key = strtolower(trim((string) $k));
            $key = ltrim($key, "\xEF\xBB\xBF"); // strip UTF-8 BOM
            $r[$key] = is_string($v) ? trim($v) : $v;
        }

        // ── Required: name ────────────────────────────────────────────────────
        $name = $r['name'] ?? $r['اسم_المنتج'] ?? $r['اسم المنتج'] ?? '';
        $name = trim((string) $name);
        if ($name === '') {
            $this->errors[] = ['row' => $rowNum, 'error' => __('pos.import_name_required')];

            return;
        }

        // ── Required: price ───────────────────────────────────────────────────
        $price = (float) ($r['price'] ?? $r['السعر'] ?? 0);
        if ($price <= 0) {
            $this->errors[] = ['row' => $rowNum, 'error' => __('pos.import_price_required', ['name' => $name])];

            return;
        }

        // ── Optional fields ───────────────────────────────────────────────────
        $barcode = (string) ($r['barcode'] ?? $r['الباركود'] ?? '');
        $category = (string) ($r['category'] ?? $r['الفئة'] ?? '');
        $costPrice = (float) ($r['cost_price'] ?? $r['سعر_التكلفة'] ?? $price);
        $wholesalePrice = (float) ($r['wholesale_price'] ?? $r['سعر_الجملة'] ?? 0);
        $vipPrice = (float) ($r['vip_price'] ?? $r['سعر_vip'] ?? 0);
        $minStock = (int) ($r['min_stock'] ?? $r['الحد_الادنى'] ?? 0);
        $initialQty = (int) ($r['initial_qty'] ?? $r['الكمية_الابتدائية'] ?? 0);
        $description = (string) ($r['description'] ?? $r['الوصف'] ?? '');
        $rawActive = strtolower((string) ($r['is_active'] ?? $r['نشط'] ?? '1'));
        $isActive = ! in_array($rawActive, ['0', 'no', 'false', 'لا', 'غير نشط', 'inactive'], true);

        $data = [
            'name' => $name,
            'barcode' => $barcode ?: null,
            'category' => $category ?: null,
            'price' => $price,
            'cost_price' => $costPrice > 0 ? $costPrice : $price,
            'wholesale_price' => $wholesalePrice > 0 ? $wholesalePrice : null,
            'vip_price' => $vipPrice > 0 ? $vipPrice : null,
            'min_stock' => max(0, $minStock),
            'description' => $description ?: null,
            'is_active' => $isActive,
        ];

        // ── Upsert ────────────────────────────────────────────────────────────
        $existing = $barcode
            ? Product::where('barcode', $barcode)->first()
            : Product::where('name', $name)->first();

        if ($existing) {
            $existing->update($data);
            $this->updatedCount++;
        } else {
            $product = DB::transaction(function () use ($data, $initialQty) {
                $p = Product::create($data);

                if ($initialQty > 0) {
                    $this->stockService->addStock(
                        $p,
                        $initialQty,
                        'opening_stock',
                        null,
                        'import',
                        $data['cost_price'],
                    );
                }

                return $p;
            });

            $this->importedCount++;
        }
    }
}
