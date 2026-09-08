<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Drawer Reconciliation - Print Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 15px;
            font-size: 11px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .header .meta {
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        th, td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.05em;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .total-row {
            border-top: 2px solid #0f172a;
            font-weight: 800;
            font-size: 11px;
        }
        .no-print {
            margin-bottom: 16px;
        }
        .btn-print {
            padding: 6px 14px;
            background: #059669;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            font-size: 11px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Report</button>
    </div>

    <div class="header">
        <div>
            <h1>Daily Cash Drawer Reconciliation Report</h1>
            <p style="margin: 3px 0 0 0; color: #64748b; font-weight: 600;">{{ config('app.name', 'Laravel POS') }}</p>
        </div>
        <div class="meta">
            <div><strong>Generated:</strong> {{ now()->format('d-m-Y H:i:s') }}</div>
            <div><strong>Total Sessions:</strong> {{ count($reconciliations) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">#</th>
                <th>Code</th>
                <th>Date</th>
                <th>Account</th>
                <th>Warehouse</th>
                <th>Opener / Closer</th>
                <th class="text-right">Starting Float ({{ $currencySymbol }})</th>
                <th class="text-right">Expected ({{ $currencySymbol }})</th>
                <th class="text-right">Counted ({{ $currencySymbol }})</th>
                <th class="text-right">Variance ({{ $currencySymbol }})</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reconciliations as $index => $r)
                <tr>
                    <td class="text-center font-mono">{{ $index + 1 }}</td>
                    <td class="font-mono"><strong>{{ $r->reconciliation_code }}</strong></td>
                    <td class="font-mono">{{ $r->reconciliation_date ? $r->reconciliation_date->format('d-m-Y') : '---' }}</td>
                    <td style="font-weight: 700;">{{ $r->account ? $r->account->account_name : '---' }}</td>
                    <td>{{ $r->warehouse ? $r->warehouse->warehouse_name : 'All Warehouses' }}</td>
                    <td>
                        {{ $r->opener ? $r->opener->name : 'Staff' }}
                        @if($r->closer && $r->closer->id !== ($r->opener->id ?? null))
                            / {{ $r->closer->name }}
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ number_format((float) $r->opening_balance, 2) }}</td>
                    <td class="text-right font-mono">{{ number_format((float) $r->expected_closing_balance, 2) }}</td>
                    <td class="text-right font-mono" style="font-weight: 700;">{{ number_format((float) $r->counted_amount, 2) }}</td>
                    <td class="text-right font-mono" style="font-weight: 700; color: {{ $r->variance < 0 ? '#dc2626' : ($r->variance > 0 ? '#2563eb' : '#059669') }};">
                        {{ number_format((float) $r->variance, 2) }}
                    </td>
                    <td class="text-center">
                        <strong style="font-size: 9px; text-transform: uppercase;">{{ $r->status }}</strong>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 24px; color: #94a3b8;">No reconciliation records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.onload = function() {
            if (!window.location.search.includes('noprint')) {
                window.print();
            }
        };
    </script>
</body>
</html>
