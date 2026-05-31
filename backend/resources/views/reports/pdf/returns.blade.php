<!DOCTYPE html>
<html dir="ltr">
<head>
<meta charset="utf-8">
<title>Returns Report</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
    h2 { margin: 0 0 2px; font-size: 16px; }
    .sub { color: #64748b; font-size: 10px; margin: 0 0 14px; }
    .stats { display: table; width: 100%; margin-bottom: 16px; }
    .stat { display: table-cell; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8px 12px; width: 33%; }
    .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; }
    .stat-val { font-size: 14px; font-weight: bold; color: #b91c1c; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
    tbody tr:nth-child(even) td { background: #f8fafc; }
    .badge-completed { color: #166534; background: #dcfce7; padding: 1px 6px; border-radius: 4px; }
    .badge-cancelled { color: #6b7280; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; }
    .footer { margin-top: 16px; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>
    <h2>Returns Report</h2>
    <p class="sub">{{ $start }} — {{ $end }}</p>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Returned Value</div>
            <div class="stat-val">{{ number_format($totals->total_returned ?? 0, 2) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Returns Count</div>
            <div class="stat-val">{{ $totals->total_count ?? 0 }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Average Return Value</div>
            <div class="stat-val">
                @php $cnt = $totals->total_count ?? 0; @endphp
                {{ $cnt > 0 ? number_format(($totals->total_returned ?? 0) / $cnt, 2) : '0.00' }}
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Return #</th>
                <th>Invoice #</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($returns as $ret)
            <tr>
                <td>{{ $ret->return_number }}</td>
                <td>{{ $ret->invoice_number ?? '-' }}</td>
                <td>{{ $ret->customer_name ?? 'Walk-in' }}</td>
                <td>{{ number_format($ret->total_amount, 2) }}</td>
                <td>{{ $ret->reason ?? '-' }}</td>
                <td><span class="{{ $ret->status === 'completed' ? 'badge-completed' : 'badge-cancelled' }}">{{ $ret->status }}</span></td>
                <td>{{ $ret->return_date }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="u-empty-state">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated: {{ now()->format('Y-m-d H:i') }} &nbsp;|&nbsp; Total records: {{ count($returns) }}</div>
</body>
</html>
