<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export products to Excel / CSV.
 *
 * Filters (all optional):
 *  - category : string — filter by category name
 *  - stock    : 'low' | 'out' | 'all'
 *  - active   : '1' | '0' | '' (all)
 *  - search   : free-text search on name / barcode
 */
class ProductsExport implements FromQuery, WithColumnFormatting, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $category = '',
        private readonly string $stock = '',
        private readonly string $active = '',
        private readonly string $search = '',
    ) {}

    public function title(): string
    {
        return 'Products';
    }

    public function query(): Builder
    {
        return Product::query()
            ->when(
                $this->search !== '',
                fn ($q) => $q->where(
                    fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")
                        ->orWhere('barcode', 'like', "%{$this->search}%"),
                ),
            )
            ->when(
                $this->category !== '',
                fn ($q) => $q->where('category', $this->category),
            )
            ->when(
                $this->active !== '',
                fn ($q) => $q->where('is_active', (bool) $this->active),
            )
            ->when(
                $this->stock === 'out',
                fn ($q) => $q->where('quantity', '<=', 0),
            )
            ->when(
                $this->stock === 'low',
                fn ($q) => $q->whereColumn('quantity', '<=', 'min_stock')->where('quantity', '>', 0),
            )
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            '#',
            'name (اسم_المنتج)',
            'barcode (الباركود)',
            'category (الفئة)',
            'price (السعر)',
            'cost_price (سعر_التكلفة)',
            'wholesale_price (سعر_الجملة)',
            'vip_price (سعر_VIP)',
            'quantity (الكمية)',
            'min_stock (الحد_الادنى)',
            'description (الوصف)',
            'is_active (نشط)',
            'created_at (تاريخ_الإضافة)',
        ];
    }

    /** @param Product $row */
    public function map($row): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $row->name,
            $row->barcode,
            $row->category,
            (float) $row->price,
            (float) $row->cost_price,
            $row->wholesale_price ? (float) $row->wholesale_price : '',
            $row->vip_price ? (float) $row->vip_price : '',
            $row->quantity,
            $row->min_stock,
            $row->description,
            $row->is_active ? 1 : 0,
            $row->created_at?->format('Y-m-d'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e293b']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 32,
            'C' => 18,
            'D' => 16,
            'E' => 12,
            'F' => 14,
            'G' => 16,
            'H' => 14,
            'I' => 10,
            'J' => 12,
            'K' => 30,
            'L' => 10,
            'M' => 14,
        ];
    }
}
