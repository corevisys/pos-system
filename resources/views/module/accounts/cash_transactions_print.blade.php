<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Transactions - Print</title>
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
            <h1>Cash Transactions Journal</h1>
            <p style="margin: 4px 0 0 0; color: #64748b; font-weight: 600;">{{ config('app.name', 'Laravel POS') }}</p>
        </div>
        <div class="meta">
            <div><strong>Generated:</strong> {{ now()->format('d-m-Y H:i:s') }}</div>
            <div><strong>Total Records:</strong> {{ count($transactions) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">#</th>
                <th>Date</th>
                <th>Type / Method</th>
                <th>Account</th>
                <th>Note</th>
                <th class="text-right">Debit ({{ $currencySymbol }})</th>
                <th class="text-right">Credit ({{ $currencySymbol }})</th>
                <th class="text-center">User</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalDebit = 0; 
                $totalCredit = 0; 
            @endphp
            @forelse($transactions as $index => $tr)
                @php 
                    $totalDebit += (float) $tr->debit_amt; 
                    $totalCredit += (float) $tr->credit_amt; 
                @endphp
                <tr>
                    <td class="text-center font-mono">{{ $index + 1 }}</td>
                    <td class="font-mono">{{ \Carbon\Carbon::parse($tr->transaction_date)->format('d-m-Y') }}</td>
                    <td>
                        <strong style="text-transform: uppercase;">{{ $tr->transaction_type }}</strong>
                        <span style="color: #64748b; font-size: 8px;">({{ $tr->payment_code ?? 'CASH' }})</span>
                    </td>
                    <td style="font-weight: 700;">
                        @if($tr->debit_amt > 0)
                            {{ $tr->debitAccount ? $tr->debitAccount->account_name : '---' }}
                        @else
                            {{ $tr->creditAccount ? $tr->creditAccount->account_name : '---' }}
                        @endif
                    </td>
                    <td style="color: #64748b;">{{ $tr->note ?? '---' }}</td>
                    <td class="text-right font-mono" style="font-weight: 700; color: {{ $tr->debit_amt > 0 ? '#dc2626' : '#94a3b8' }};">
                        {{ number_format((float) $tr->debit_amt, 2) }}
                    </td>
                    <td class="text-right font-mono" style="font-weight: 700; color: {{ $tr->credit_amt > 0 ? '#059669' : '#94a3b8' }};">
                        {{ number_format((float) $tr->credit_amt, 2) }}
                    </td>
                    <td class="text-center">{{ $tr->creator ? $tr->creator->name : 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 24px; color: #94a3b8;">No transactions found</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($transactions) > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="5" class="text-right" style="padding: 10px;">TOTALS:</td>
                    <td class="text-right font-mono" style="padding: 10px; color: #dc2626;">{{ number_format($totalDebit, 2) }}</td>
                    <td class="text-right font-mono" style="padding: 10px; color: #059669;">{{ number_format($totalCredit, 2) }}</td>
                    <td></td>
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
