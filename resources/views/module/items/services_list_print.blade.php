<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services List</title>
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

        .print-actions {
            text-align: right;
        }

        .print-actions button {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        thead th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        tbody td {
            font-size: 10px;
            color: #1e293b;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
        }

        .text-right {
            text-align: right;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .no-data {
            text-align: center;
            color: #94a3b8;
            padding: 24px 0;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
    </style>
</head>

<body>
    <div class="list-container">
        <div class="list-header">
            <div>
                <h1>Services List</h1>
                <div class="meta">Generated on {{ now()->format('d M Y h:i A') }}</div>
            </div>
            <div class="print-actions no-print">
                <button onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Service Name</th>
                    <th>Category</th>
                    <th class="text-right">Base Price</th>
                    <th class="text-right">Sales Price</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                    <tr>
                        <td>{{ $service->item_code }}</td>
                        <td>{{ $service->item_name }}</td>
                        <td>{{ $service->category->category_name ?? '---' }}</td>
                        <td class="text-right">{{ number_format((float) $service->price, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $service->sales_price, 2) }}</td>
                        <td>
                            <span class="{{ $service->status == 1 ? 'badge-active' : 'badge-inactive' }}">
                                {{ $service->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="no-data">No services found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>

</html>
