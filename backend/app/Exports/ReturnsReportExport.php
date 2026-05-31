<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReturnsReportExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private Collection $returns) {}

    public function collection(): Collection
    {
        return $this->returns->map(fn ($r) => [
            $r->return_number,
            $r->invoice_number ?? '-',
            $r->customer_name ?? 'Walk-in',
            number_format($r->total_amount, 2),
            $r->reason ?? '-',
            $r->status,
            $r->return_date,
        ]);
    }

    public function headings(): array
    {
        return ['Return #', 'Invoice #', 'Customer', 'Amount', 'Reason', 'Status', 'Date'];
    }
}
