<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense List - Print</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #1e293b; margin: 0; padding: 20px; font-size: 11px; background: #fff; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; }
        .header .meta { text-align: right; font-size: 10px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        th { background-color: #f8fafc; color: #475569; font-weight: 800; text-transform: uppercase; font-size: 9px; letter-spacing: 0.05em; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .total-row { border-top: 2px solid #0f172a; font-weight: 800; font-size: 11px; }
        .no-print { margin-bottom: 20px; }
        .btn-print { padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 11px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Report</button>
    </div>

    <div class="header">
        <div>
            <h1>Expense List</h1>
            <p style="margin: 4px 0 0 0; color: #64748b; font-weight: 600;">{{ config('app.name', 'Laravel POS') }}</p>
        </div>
        <div class="meta">
            <div><strong>Generated:</strong> {{ now()->format('d-m-Y H:i:s') }}</div>
            <div><strong>Total Records:</strong> {{ count($expenses) }}</div>
            <div><strong>Total Amount:</strong> {{ number_format((float) ($totalExpenses ?? 0), 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">#</th>
                <th>Date</th>
                <th>Code</th>
                <th>Category</th>
                <th>Reference No.</th>
                <th>Expense For</th>
                <th>Payment Type</th>
                <th class="text-right">Amount</th>
                <th>Account</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $i => $expense)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('d-m-Y') }}</td>
                    <td class="font-mono">{{ $expense->expense_code ?? '---' }}</td>
                    <td>{{ $expense->category ? $expense->category->category_name : 'N/A' }}</td>
                    <td class="font-mono">{{ $expense->reference_no ?? '---' }}</td>
                    <td>{{ $expense->expense_for }}</td>
                    <td>{{ $expense->payment_type ?? 'Cash' }}</td>
                    <td class="text-right font-mono">{{ number_format((float) $expense->expense_amt, 2, '.', ',') }}</td>
                    <td>{{ $expense->account ? $expense->account->account_name : '---' }}</td>
                </tr>
            @endforeach
        </tbody>
        @if($expenses->isNotEmpty())
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-right">Total Expenses</td>
                <td class="text-right font-mono">{{ number_format((float) ($totalExpenses ?? $expenses->sum('expense_amt')), 2, '.', ',') }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
