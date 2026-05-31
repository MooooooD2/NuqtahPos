<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Downloadable template for bulk product import.
 * Includes two example rows (Arabic + English) so the user
 * can see accepted formats immediately.
 */
class ProductsTemplateExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Products';
    }

    public function headings(): array
    {
        return [
            'name (اسم_المنتج) *',
            'barcode (الباركود)',
            'category (الفئة)',
            'price (السعر) *',
            'cost_price (سعر_التكلفة)',
            'wholesale_price (سعر_الجملة)',
            'vip_price (سعر_VIP)',
            'min_stock (الحد_الادنى)',
            'initial_qty (الكمية_الابتدائية)',
            'description (الوصف)',
            'is_active (نشط) 1/0',
        ];
    }

    public function array(): array
    {
        return [
            // Arabic example row
            ['أرز بسمتي 1 كيلو', '6281234567890', 'حبوب', 25.00, 18.00, 22.00, 24.00, 10, 50, 'أرز بسمتي فاخر', 1],
            // English example row
            ['Mineral Water 1L',  '6289876543210', 'Beverages', 5.50, 3.00, 4.50, 5.00, 20, 100, 'Still mineral water', 1],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row: dark background, white bold text
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e293b']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            ],
            // Example rows: light blue tint so they're clearly identifiable
            2 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']]],
            3 => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,  // name
            'B' => 18,  // barcode
            'C' => 16,  // category
            'D' => 12,  // price
            'E' => 14,  // cost_price
            'F' => 18,  // wholesale_price
            'G' => 14,  // vip_price
            'H' => 14,  // min_stock
            'I' => 20,  // initial_qty
            'J' => 30,  // description
            'K' => 14,  // is_active
        ];
    }
}
