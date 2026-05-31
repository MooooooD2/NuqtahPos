<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProfitableProductsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private Collection $products) {}

    public function title(): string
    {
        return 'Profitable Products';
    }

    public function headings(): array
    {
        return ['#', 'Product', 'Category', 'Barcode', 'Qty Sold', 'Revenue', 'Cost', 'Gross Profit', 'Margin %'];
    }

    public function collection(): Collection
    {
        return $this->products->values()->map(fn ($p, $i) => [
            $i + 1,
            $p->product_name,
            $p->category ?? '',
            $p->barcode ?? '',
            $p->total_qty,
            number_format($p->total_revenue, 2),
            number_format($p->total_cost, 2),
            number_format($p->gross_profit, 2),
            $p->profit_margin . '%',
        ]);
    }
}
