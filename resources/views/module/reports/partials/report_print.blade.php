<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; margin: 24px; color: #1e293b; }
        .rp-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 16px; }
        .rp-title { font-size: 18px; font-weight: 800; margin: 0; }
        .rp-sub { font-size: 11px; color: #64748b; margin-top: 4px; }
        .rp-meta { font-size: 11px; color: #475569; text-align: right; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        thead th { background: #f1f5f9; text-align: left; padding: 7px 8px; border-bottom: 1px solid #cbd5e1; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; font-size: 9px; color: #475569; }
        tbody td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .rp-empty { text-align: center; color: #94a3b8; padding: 28px; font-weight: 600; }
        .rp-actions { margin-bottom: 14px; }
        .rp-print-btn { background: #0f172a; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; cursor: pointer; }
        @media print { .rp-actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="rp-actions">
        <button class="rp-print-btn" onclick="window.print()">Print</button>
    </div>

    <div class="rp-header">
        <div>
            <h1 class="rp-title">{{ $title }}</h1>
            <div class="rp-sub">Generated {{ now()->format('M d, Y H:i') }}</div>
        </div>
        @if(!empty($meta))
            <div class="rp-meta">
                @foreach($meta as $label => $value)
                    <div><strong>{{ $label }}:</strong> {{ $value }}</div>
                @endforeach
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach(array_keys($columns) as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($records as $row)
                <tr>
                    @foreach($columns as $key)
                        <td>{{ is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="rp-empty" colspan="{{ count($columns) }}">No records found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
