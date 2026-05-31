<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockReportExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private Collection $products) {}

    public function collection(): Collection
    {
        return $this->products->map(fn ($p) => [
            $p['name'],
            $p['category'] ?? '-',
            $p['quantity'],
            number_format($p['cost_price'], 2),
            number_format($p['price'], 2),
            number_format($p['stock_value'], 2),
            $p['quantity'] == 0 ? 'Out of Stock' : ($p['low_stock'] ? 'Low Stock' : 'OK'),
        ]);
    }

    public function headings(): array
    {
        return ['Product', 'Category', 'Qty', 'Cost Price', 'Sell Price', 'Stock Value', 'Status'];
    }
}
