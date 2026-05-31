<!DOCTYPE html>
<html dir="ltr">
<head>
<meta charset="utf-8">
<title>Stock Report</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
    h2 { margin: 0 0 2px; font-size: 16px; }
    .sub { color: #64748b; font-size: 10px; margin: 0 0 14px; }
    .stats { display: table; width: 100%; margin-bottom: 16px; }
    .stat { display: table-cell; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8px 12px; width: 33%; }
    .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; }
    .stat-val { font-size: 14px; font-weight: bold; color: #1e40af; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
    tbody tr:nth-child(even) td { background: #f8fafc; }
    .ok { color: #166534; } .low { color: #92400e; } .out { color: #991b1b; }
    .footer { margin-top: 16px; font-size: 9px; color: #94a3b8; }
</style>
</head>
<body>
    <h2>Stock Report</h2>
    <p class="sub">Generated: {{ now()->format('Y-m-d H:i') }}</p>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Stock Value</div>
            <div class="stat-val">{{ number_format($total_stock_value, 2) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Low Stock Products</div>
            <div class="stat-val" style="color:#92400e">{{ $low_stock_count }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Out of Stock</div>
            <div class="stat-val" style="color:#991b1b">{{ $out_of_stock }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Cost Price</th>
                <th>Sell Price</th>
                <th>Stock Value</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            <tr>
                <td>{{ $p['name'] }}</td>
                <td>{{ $p['category'] ?? '-' }}</td>
                <td>{{ $p['unit_abbreviation'] ?? $p['unit_name'] ?? '-' }}</td>
                <td class="{{ $p['quantity'] == 0 ? 'out' : ($p['low_stock'] ? 'low' : 'ok') }}">{{ $p['quantity'] }}</td>
                <td>{{ number_format($p['cost_price'], 2) }}</td>
                <td>{{ number_format($p['price'], 2) }}</td>
                <td>{{ number_format($p['stock_value'], 2) }}</td>
                <td class="{{ $p['quantity'] == 0 ? 'out' : ($p['low_stock'] ? 'low' : 'ok') }}">
                    {{ $p['quantity'] == 0 ? 'Out of Stock' : ($p['low_stock'] ? 'Low Stock' : 'OK') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:12px">No data</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Total products: {{ count($products) }}</div>
</body>
</html>
