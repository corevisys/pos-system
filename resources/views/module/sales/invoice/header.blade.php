<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <span>{{ $sale->sales_code }} | {{ date('d-M-Y', strtotime($sale->sales_date)) }}</span>
        <span>{{ $store->store_name }}</span>
    </div>
</body>
</html>
