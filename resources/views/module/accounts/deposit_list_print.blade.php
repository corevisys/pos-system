<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit List - Print</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            font-size: 11px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
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
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px 10px;
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
            margin-bottom: 20px;
        }
        .btn-print {
            padding: 8px 16px;
            background: #2563eb;
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
            <h1>Deposit List</h1>
            <p style="margin: 4px 0 0 0; color: #64748b; font-weight: 600;">{{ config('app.name', 'Laravel POS') }}</p>
        </div>
        <div class="meta">
            <div><strong>Generated:</strong> {{ now()->format('d-m-Y H:i:s') }}</div>
            <div><strong>Total Records:</strong> {{ count($deposits) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">#</th>
                <th>Date</th>
                <th>Reference No.</th>
                <th>Debit Account (Source)</th>
                <th>Credit Account (Destination)</th>
                <th class="text-right">Amount ({{ $currencySymbol }})</th>
                <th class="text-center">Creator</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @php $totalAmount = 0; @endphp
            @forelse($deposits as $index => $dep)
                @php $totalAmount += (float) $dep->amount; @endphp
                <tr>
                    <td class="text-center font-mono">{{ $index + 1 }}</td>
                    <td class="font-mono">{{ \Carbon\Carbon::parse($dep->deposit_date)->format('d-m-Y') }}</td>
                    <td class="font-mono font-bold">{{ $dep->reference_no ?? '---' }}</td>
                    <td style="color: #dc2626; font-weight: 700;">{{ $dep->debitAccount ? $dep->debitAccount->account_name : 'External' }}</td>
                    <td style="color: #059669; font-weight: 700;">{{ $dep->creditAccount ? $dep->creditAccount->account_name : '---' }}</td>
                    <td class="text-right font-mono" style="font-weight: 800;">{{ number_format((float) $dep->amount, 2) }}</td>
                    <td class="text-center">{{ $dep->creator ? $dep->creator->name : 'System' }}</td>
                    <td style="color: #64748b;">{{ $dep->note ?? '---' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 24px; color: #94a3b8;">No deposits found</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($deposits) > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" class="text-right" style="padding: 10px;">TOTAL DEPOSITED:</td>
                    <td class="text-right font-mono" style="padding: 10px; color: #059669;">{{ number_format($totalAmount, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
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
