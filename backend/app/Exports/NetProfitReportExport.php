<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class NetProfitReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private array $data, private string $start, private string $end) {}

    public function title(): string
    {
        return 'Net Profit';
    }

    public function headings(): array
    {
        return ['Metric', 'Value', 'vs Prev. Period'];
    }

    public function array(): array
    {
        $cmp = $this->data['comparison'] ?? [];

        $changePct = function ($pct): string {
            if ($pct === null) {
                return '';
            }
            $arrow = $pct > 0 ? '▲' : ($pct < 0 ? '▼' : '→');

            return $arrow . ' ' . abs($pct) . '%';
        };

        return [
            ['Period', "{$this->start} – {$this->end}", ''],
            ['Prev. Period', ($cmp['prev_start_date'] ?? '') . ' – ' . ($cmp['prev_end_date'] ?? ''), ''],
            ['', '', ''],
            ['Gross Sales',          number_format($this->data['gross_sales'] ?? 0, 2), ''],
            ['Discounts',            '-' . number_format($this->data['discounts'] ?? 0, 2), ''],
            ['Tax Collected',        '-' . number_format($this->data['tax'] ?? 0, 2), ''],
            ['Returns',              '-' . number_format($this->data['returns'] ?? 0, 2), ''],
            ['Net Revenue',          number_format($this->data['net_revenue'] ?? 0, 2), ''],
            ['COGS',                 '-' . number_format($this->data['cogs'] ?? 0, 2), $changePct($cmp['cogs_change_pct'] ?? null)],
            ['Gross Profit',         number_format($this->data['gross_profit'] ?? 0, 2), $changePct($cmp['gross_profit_change_pct'] ?? null)],
            ['Gross Margin %',       ($this->data['gross_margin_pct'] ?? 0) . '%', ''],
            ['Operating Expenses',   '-' . number_format($this->data['operating_expenses'] ?? 0, 2), $changePct($cmp['operating_expenses_change_pct'] ?? null)],
            ['Net Profit',           number_format($this->data['net_profit'] ?? 0, 2), $changePct($cmp['net_profit_change_pct'] ?? null)],
            ['Net Margin %',         ($this->data['net_margin_pct'] ?? 0) . '%', ''],
        ];
    }
}
