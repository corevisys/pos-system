<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMI Sales List</title>
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

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }

        table th,
        table td {
            border: 1px solid #e2e8f0;
            padding: 5px 7px;
            text-align: left;
            vertical-align: top;
        }

        table th {
            background: #f1f5f9;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-size: 8px;
            color: #334155;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row td {
            font-weight: 900;
            background: #f8fafc;
        }

        .no-print {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 9999;
            background: #0f172a;
            padding: 8px 14px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .no-print button {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
        }

        .no-print button.close {
            background: #334155;
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
        <button class="close" onclick="window.close()">Close</button>
    </div>

    <div class="list-container">
        <div class="list-header">
            <div>
                <h1>EMI Sales List</h1>
                <div class="meta">
                    {{ count($emiSales) }} EMI sale(s)
                    @if(request('warehouse_id')) &middot; Warehouse filter applied @endif
                    @if(request('customer_id')) &middot; Customer filter applied @endif
                    @if(request('search')) &middot; Search "{{ request('search') }}" @endif
                </div>
            </div>
            <div class="meta">Generated {{ date('d-m-Y H:i') }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Start Date</th>
                    <th>Sales Code</th>
                    <th>Customer</th>
                    <th class="text-right">Loan Amount</th>
                    <th class="text-right">Total Payable</th>
                    <th class="text-center">Duration</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($emiSales as $emi)
                    <tr>
                        <td>{{ date('d-m-Y', strtotime($emi->start_date)) }}</td>
                        <td>{{ $emi->sale->sales_code ?? '---' }}</td>
                        <td>{{ $emi->customer->customer_name ?? 'Walk-in' }}</td>
                        <td class="text-right">{{ number_format((float) $emi->loan_amount, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $emi->total_payable, 2) }}</td>
                        <td class="text-center">{{ $emi->duration_months }} Months</td>
                        <td class="text-center">{{ $emi->status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No EMI sales found for the applied filters.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right">Total Summary</td>
                    <td class="text-right">{{ number_format((float) $globalStats['total_loan'], 2) }}</td>
                    <td class="text-right">{{ number_format((float) $globalStats['total_payable'], 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <script>
        window.onload = function () {
            setTimeout(function () {
                window.print();
            }, 200);
        };
    </script>
</body>

</html>
