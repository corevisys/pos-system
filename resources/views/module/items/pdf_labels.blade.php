<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Barcode Labels</title>
    <style>
        @if($preset === 'thermal_roll')
            @page {
                margin: 1mm;
                size: 50mm 25mm;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: #ffffff;
            }
            .thermal-label {
                width: 48mm;
                height: 23mm;
                text-align: center;
                box-sizing: border-box;
                page-break-inside: avoid;
                page-break-after: always;
            }
            .store { font-size: 7pt; font-weight: bold; text-transform: uppercase; margin-bottom: 0.5mm; white-space: nowrap; overflow: hidden; }
            .name { font-size: 6.5pt; font-weight: bold; line-height: 1.1; margin-bottom: 0.5mm; max-height: 6mm; overflow: hidden; }
            .price { font-size: 7.5pt; font-weight: bold; margin-bottom: 0.5mm; }
            .barcode-img { max-width: 90%; height: 7mm; display: block; margin: 0 auto 0.5mm auto; }
            .code { font-family: monospace; font-size: 6pt; font-weight: bold; letter-spacing: 0.1em; }
        @else
            @page {
                margin: 6mm 5mm;
                size: A4 portrait;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: #ffffff;
            }
            table.sheet-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 2mm 2.5mm;
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            td.label-card {
                border: 1px dashed #94a3b8;
                text-align: center;
                vertical-align: middle;
                box-sizing: border-box;
                page-break-inside: avoid;
            }

            @if($preset === 'sheet_24')
                td.label-card { width: 33.33%; height: 33.5mm; padding: 1.5mm; }
                .store { font-size: 8pt; font-weight: bold; text-transform: uppercase; margin-bottom: 0.5mm; }
                .name { font-size: 7.5pt; font-weight: bold; line-height: 1.1; margin-bottom: 0.5mm; max-height: 7mm; overflow: hidden; }
                .price { font-size: 8pt; font-weight: bold; margin-bottom: 0.5mm; }
                .barcode-img { max-width: 90%; height: 8.5mm; display: block; margin: 0 auto 0.5mm auto; }
                .code { font-family: monospace; font-size: 6.5pt; font-weight: bold; letter-spacing: 0.1em; }
            @elseif($preset === 'sheet_30')
                td.label-card { width: 33.33%; height: 27.0mm; padding: 1mm; }
                .store { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; margin-bottom: 0.3mm; }
                .name { font-size: 7pt; font-weight: bold; line-height: 1.1; margin-bottom: 0.3mm; max-height: 6mm; overflow: hidden; }
                .price { font-size: 7.5pt; font-weight: bold; margin-bottom: 0.3mm; }
                .barcode-img { max-width: 90%; height: 7.5mm; display: block; margin: 0 auto 0.3mm auto; }
                .code { font-family: monospace; font-size: 6pt; font-weight: bold; letter-spacing: 0.1em; }
            @elseif($preset === 'sheet_12')
                td.label-card { width: 50%; height: 45.0mm; padding: 2.5mm; }
                .store { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin-bottom: 1mm; }
                .name { font-size: 9.5pt; font-weight: bold; line-height: 1.15; margin-bottom: 1mm; max-height: 10mm; overflow: hidden; }
                .price { font-size: 11pt; font-weight: bold; margin-bottom: 1mm; }
                .barcode-img { max-width: 90%; height: 13mm; display: block; margin: 0 auto 0.5mm auto; }
                .code { font-family: monospace; font-size: 8.5pt; font-weight: bold; letter-spacing: 0.1em; }
            @elseif($preset === 'sheet_40')
                td.label-card { width: 25%; height: 24.5mm; padding: 0.8mm; }
                .store { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; margin-bottom: 0.2mm; }
                .name { font-size: 6pt; font-weight: bold; line-height: 1.05; margin-bottom: 0.2mm; max-height: 5mm; overflow: hidden; }
                .price { font-size: 6.5pt; font-weight: bold; margin-bottom: 0.2mm; }
                .barcode-img { max-width: 92%; height: 6.5mm; display: block; margin: 0 auto 0.2mm auto; }
                .code { font-family: monospace; font-size: 5.5pt; font-weight: bold; letter-spacing: 0.1em; }
            @endif
        @endif

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
@if(!empty($autoprint))
    <div class="no-print" style="position: fixed; top: 12px; right: 12px; z-index: 9999; background: #0f172a; padding: 8px 14px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); display: flex; gap: 8px; align-items: center;">
        <button onclick="window.print()" style="background: #2563eb; color: #ffffff; border: none; padding: 6px 14px; border-radius: 8px; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer;">Print Labels</button>
        <button onclick="window.close()" style="background: #334155; color: #ffffff; border: none; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 11px; cursor: pointer;">Close</button>
    </div>
@endif

@if($preset === 'thermal_roll')
    @foreach($stickers as $sticker)
        <div class="thermal-label">
            @if($showStore)<div class="store">{{ $storeName }}</div>@endif
            @if($showName)<div class="name">{{ $sticker['name'] }}</div>@endif
            @if($showPrice)<div class="price">Price: {{ $sticker['formatted_price'] }}</div>@endif
            @if($showBarcode && !empty($sticker['barcode_png']))<img class="barcode-img" src="{{ $sticker['barcode_png'] }}" />@endif
            @if($showCode)<div class="code">{{ $sticker['barcode_value'] }}</div>@endif
        </div>
    @endforeach
@else
    @php
        $totalStickers = count($stickers);
    @endphp
    <table class="sheet-table">
        @for($i = 0; $i < $totalStickers; $i += $cols)
            <tr>
                @for($c = 0; $c < $cols; $c++)
                    @php $idx = $i + $c; @endphp
                    @if($idx < $totalStickers)
                        @php $sticker = $stickers[$idx]; @endphp
                        <td class="label-card">
                            @if($showStore)<div class="store">{{ $storeName }}</div>@endif
                            @if($showName)<div class="name">{{ $sticker['name'] }}</div>@endif
                            @if($showPrice)<div class="price">Price: {{ $sticker['formatted_price'] }}</div>@endif
                            @if($showBarcode && !empty($sticker['barcode_png']))<img class="barcode-img" src="{{ $sticker['barcode_png'] }}" />@endif
                            @if($showCode)<div class="code">{{ $sticker['barcode_value'] }}</div>@endif
                        </td>
                    @else
                        <td class="label-card" style="border: none;"></td>
                    @endif
                @endfor
            </tr>
        @endfor
    </table>
@endif

@if(!empty($autoprint))
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 200);
        };
    </script>
@endif
</body>
</html>
