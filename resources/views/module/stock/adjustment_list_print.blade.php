<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Adjustment List</title>
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
    <h1>Stock Adjustment List</h1>
    <div class="meta">Generated: {{ now()->format('d-m-Y H:i:s') }} — {{ $adjustments->count() }} record(s)</div>

    <table>
        <thead>
            <tr>
                <th>Adjustment Date</th>
                <th>Reference No.</th>
                <th>Warehouse</th>
                <th>Created By</th>
                <th>Reason / Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($adjustments as $adj)
                <tr>
                    <td>{{ $adj->adjustment_date }}</td>
                    <td>{{ $adj->reference_no ?? '---' }}</td>
                    <td>{{ $adj->warehouse->warehouse_name ?? '---' }}</td>
                    <td>{{ $adj->user->name ?? 'System' }}</td>
                    <td>{{ $adj->adjustment_note ?? '---' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center">No adjustments found</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.print();</script>
</body>
</html>
