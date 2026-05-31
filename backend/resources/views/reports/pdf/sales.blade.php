<!DOCTYPE html>
<html dir="ltr">
<head>
<meta charset="utf-8">
<title>Sales Report</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
    h2 { margin: 0 0 2px; font-size: 16px; }
    .sub { color: #64748b; font-size: 10px; margin: 0 0 14px; }
    .stats { display: table; width: 100%; margin-bottom: 16px; }
    .stat { display: table-cell; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8px 12px; width: 25%; }
    .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; }
    .stat-val { font-size: 14px; font-weight: bold; color: #1e40af; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
    tbody tr:nth-child(even) td { background: #f8fafc; }
    .footer { margin-top: 16px; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>
    <h2>Sales Report</h2>
    <p class="sub">{{ $start }} — {{ $end }}</p>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-val">{{ number_format($totals->total_revenue ?? 0, 2) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Invoices</div>
            <div class="stat-val">{{ $totals->total_count ?? 0 }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Discount</div>
            <div class="stat-val">{{ number_format($totals->total_discount ?? 0, 2) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Total Tax</div>
            <div class="stat-val">{{ number_format($totals->total_tax ?? 0, 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Total</th>
                <th>Discount</th>
                <th>Final Total</th>
                <th>Payment</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
            <tr>
                <td>{{ $inv->invoice_number }}</td>
                <td>{{ number_format($inv->total, 2) }}</td>
                <td>{{ number_format($inv->discount, 2) }}</td>
                <td>{{ number_format($inv->final_total, 2) }}</td>
                <td>{{ $inv->payment_method }}</td>
                <td>{{ $inv->created_at->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="u-empty-state">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated: {{ now()->format('Y-m-d H:i') }} &nbsp;|&nbsp; Total records: {{ count($invoices) }}</div>
</body>
</html>
