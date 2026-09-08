<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts List</title>
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
            margin: 0;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 0.02em;
            color: #0f172a;
        }

        .list-header .meta {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }

        .list-header .actions {
            text-align: right;
        }

        .list-header .actions .btn-print {
            display: inline-block;
            padding: 6px 14px;
            background: #0f172a;
            color: white;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        table th {
            background: #f1f5f9;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
        }

        table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            color: #0f172a;
        }

        table tfoot td {
            background: #f8fafc;
            font-weight: 800;
            border-top: 2px solid #0f172a;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge-active,
        .badge-inactive {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 99px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
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
                <h1>Accounts List</h1>
                <div class="meta">
                    Generated: {{ now()->format('d-m-Y h:i A') }} &middot; {{ count($accounts) }} account(s)
                </div>
            </div>
            <div class="actions no-print">
                <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th>Parent Account</th>
                    <th class="text-right">Balance</th>
                    <th class="text-center">Created By</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $acc)
                    <tr>
                        <td>{{ $acc->account_code }}</td>
                        <td>{{ $acc->account_name }}</td>
                        <td>{{ $acc->parent ? $acc->parent->account_name : '---' }}</td>
                        <td class="text-right">{{ number_format((float) $acc->balance, 2) }}</td>
                        <td class="text-center">{{ $acc->creator ? $acc->creator->name : 'System' }}</td>
                        <td class="text-center">
                            <span class="{{ $acc->status == 1 ? 'badge-active' : 'badge-inactive' }}">
                                {{ $acc->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 20px;">No accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right">Total Balance</td>
                    <td class="text-right">{{ number_format($totalBalance, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-note">Generated by {{ auth()->user()->name ?? 'System' }} — Corevisys POS Intel</div>
    </div>
</body>

</html>
