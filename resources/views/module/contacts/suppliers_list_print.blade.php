<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers List</title>
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
            size: A4 landscape;
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
            max-width: 297mm;
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
                <h1>Suppliers List</h1>
                <div class="meta">
                    Generated: {{ now()->format('d-m-Y h:i A') }} &middot; {{ count($suppliers) }} supplier(s)
                </div>
            </div>
            <div class="actions no-print">
                <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Supplier Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Location</th>
                    <th class="text-right">Previous Bal</th>
                    <th class="text-right">Purchase Due</th>
                    <th class="text-right">Return Due</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $s)
                    <tr>
                        <td>{{ $s->supplier_code }}</td>
                        <td>{{ $s->supplier_name }}</td>
                        <td>{{ $s->mobile }}</td>
                        <td>{{ $s->email ?? '' }}</td>
                        <td>{{ $s->city ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) $s->opening_balance, 2) }}</td>
                        <td class="text-right">{{ number_format((float) ($s->live_purchase_due ?? 0), 2) }}</td>
                        <td class="text-right">{{ number_format((float) ($s->live_return_due ?? 0), 2) }}</td>
                        <td class="text-center">
                            <span class="{{ $s->status == 1 ? 'badge-active' : 'badge-inactive' }}">
                                {{ $s->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 20px;">No suppliers found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">Total Summary</td>
                    <td class="text-right">{{ number_format($totalOpeningBalance, 2) }}</td>
                    <td class="text-right">{{ number_format($totalPurchaseDue, 2) }}</td>
                    <td class="text-right">{{ number_format($totalReturnDue, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-note">Generated by {{ auth()->user()->name ?? 'System' }} — Corevisys POS Intel</div>
    </div>
</body>

</html>
