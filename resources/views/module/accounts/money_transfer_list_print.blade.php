<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Transfer List</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-print-color-adjust: exact;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 8.5pt !important;
                line-height: 1.3 !important;
            }

            .list-container {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            table {
                font-size: 8pt !important;
            }
        }

        .list-container {
            margin: 1rem auto;
            background: white;
            padding: 10mm;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 210mm;
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .list-header h1 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .list-header .meta {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-print {
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-print:hover {
            background-color: #4338ca;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 7px 8px;
            text-align: left;
            border-bottom: 1.5px solid #cbd5e1;
        }

        td {
            padding: 6px 8px;
            font-size: 9px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }

        tfoot td {
            background-color: #f8fafc;
            font-weight: 800;
            border-top: 1.5px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            font-size: 9.5px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer-note {
            margin-top: 12px;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="list-container">
        <div class="list-header">
            <div>
                <h1>Money Transfer List</h1>
                <div class="meta">
                    Generated: {{ now()->format('d-m-Y h:i A') }} &middot; {{ count($transfers) }} transfer(s)
                </div>
            </div>
            <div class="actions no-print">
                <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Transfer Code</th>
                    <th>Date</th>
                    <th>Reference No.</th>
                    <th>From Account</th>
                    <th>To Account</th>
                    <th class="text-right">Amount</th>
                    <th class="text-center">Creator</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transfers as $tr)
                    <tr>
                        <td style="font-family: monospace; font-weight: bold;">{{ $tr->transfer_code }}</td>
                        <td>{{ \Carbon\Carbon::parse($tr->transfer_date)->format('d-m-Y') }}</td>
                        <td>{{ $tr->reference_no ?? '---' }}</td>
                        <td style="color: #e11d48; font-weight: 600;">{{ $tr->debitAccount ? $tr->debitAccount->account_name : '---' }}</td>
                        <td style="color: #059669; font-weight: 600;">{{ $tr->creditAccount ? $tr->creditAccount->account_name : '---' }}</td>
                        <td class="text-right" style="font-weight: 700;">{{ number_format((float) $tr->amount, 2) }}</td>
                        <td class="text-center">{{ $tr->creator ? $tr->creator->name : 'System' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px;">No transfers found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">Total Transferred Amount</td>
                    <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-note">Generated by {{ auth()->user()->name ?? 'System' }} — Corevisys POS Intel</div>
    </div>
</body>

</html>
