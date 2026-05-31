<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesReportExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private Collection $invoices) {}

    public function collection(): Collection
    {
        return $this->invoices->map(fn ($inv) => [
            $inv->invoice_number,
            number_format($inv->total, 2),
            number_format($inv->discount, 2),
            number_format($inv->final_total, 2),
            $inv->payment_method,
            $inv->created_at->format('Y-m-d H:i'),
        ]);
    }

    public function headings(): array
    {
        return ['Invoice #', 'Total', 'Discount', 'Final Total', 'Payment Method', 'Date'];
    }
}
