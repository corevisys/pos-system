<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Transfer List</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #555; font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; font-size: 11px; }
        th { background: #f3f4f6; }
        .qty { text-align: right; }
        @media print { body { margin: 8px; } }
    </style>
</head>
<body>
    <h1>Stock Transfer List</h1>
    <div class="meta">Generated: {{ now()->format('d-m-Y H:i:s') }} — {{ $transfers->count() }} record(s)</div>

    <table>
        <thead>
            <tr>
                <th>Transfer Date</th>
                <th>Reference No.</th>
                <th>From Warehouse</th>
                <th>To Warehouse</th>
                <th>Items</th>
                <th class="qty">Total Qty</th>
                <th>Note</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transfers as $tr)
                <tr>
                    <td>{{ $tr->transfer_date }}</td>
                    <td>{{ $tr->reference_no ?? '---' }}</td>
                    <td>{{ $tr->fromWarehouse->warehouse_name ?? '---' }}</td>
                    <td>{{ $tr->toWarehouse->warehouse_name ?? '---' }}</td>
                    <td>{{ $tr->items->count() }}</td>
                    <td class="qty">{{ format_quantity($tr->items->sum('transfer_qty')) }}</td>
                    <td>{{ $tr->note ?? '---' }}</td>
                    <td>{{ $tr->creator->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center">No transfers found</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.print();</script>
</body>
</html>
