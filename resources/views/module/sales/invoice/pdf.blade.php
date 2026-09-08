<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - {{ $sale->sales_code }}</title>
    <style>
        @page {
            @if($paperSize === 'letter')
                size: letter portrait;
            @else
                size: a4 portrait;
            @endif
            margin: 8mm 8mm 8mm 8mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.3;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 7px;
            margin-bottom: 9px;
        }

        .store-name {
            font-size: 14pt;
            font-weight: 900;
            text-transform: uppercase;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .store-sub {
            font-size: 6pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.25em;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-title {
            font-size: 15pt;
            font-weight: 900;
            font-style: italic;
            text-transform: uppercase;
            color: #cbd5e1;
            text-align: right;
            margin: 0;
        }

        .info-table {
            margin-bottom: 9px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 7px;
        }

        .info-col-left {
            width: 60%;
            vertical-align: top;
            font-size: 7.5pt;
            color: #475569;
        }

        .info-col-right {
            width: 40%;
            vertical-align: top;
            text-align: right;
        }

        .micro-label {
            font-size: 6.5pt;
            font-weight: 800;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.05em;
        }

        .micro-val {
            font-size: 8pt;
            font-weight: 700;
            color: #0f172a;
        }

        .recipient-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 9px;
            padding: 7px 9px;
        }

        .product-table {
            width: 100%;
            border: 1px solid #0f172a;
            border-collapse: collapse;
            margin-bottom: 9px;
        }

        .product-table th {
            background-color: #ffffff;
            color: #0f172a;
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 7px;
            border-bottom: 2px solid #0f172a;
            border-right: 1px solid #e2e8f0;
        }

        .product-table th:last-child {
            border-right: none;
        }

        .product-table td {
            font-size: 8pt;
            padding: 6px 7px;
            border-bottom: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .product-table td:last-child {
            border-right: none;
        }

        .emi-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 7px 9px;
            margin-bottom: 9px;
        }

        .summary-table {
            width: 100%;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .summary-left {
            width: 58%;
            vertical-align: top;
            padding-right: 12px;
        }

        .summary-right {
            width: 42%;
            vertical-align: top;
        }

        .totals-card {
            border: 1px solid #0f172a;
            border-radius: 4px;
            background: #ffffff;
            padding: 7px 9px;
        }

        .totals-row {
            width: 100%;
            font-size: 7.5pt;
            font-weight: 700;
            color: #475569;
            margin-bottom: 3px;
        }

        .grand-total-row {
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 5px 0;
            margin: 4px 0;
            font-size: 10.5pt;
            font-weight: 900;
            color: #0f172a;
        }

        .settlement-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px 7px;
            border-radius: 3px;
            margin-top: 5px;
        }

        .words-box {
            background-color: #f8fafc;
            border-left: 3px solid #94a3b8;
            padding: 5px 9px;
            font-size: 7.5pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            font-style: italic;
            margin-top: 3px;
            margin-bottom: 14px;
        }

        .sig-table {
            width: 100%;
            margin-top: 18px;
        }

        .sig-line {
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            text-align: center;
            font-size: 6pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
        }

        .footer-bar {
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            margin-top: 9px;
            font-size: 6pt;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
        }

        .badge-settled {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 1.5px 5px;
            border-radius: 2px;
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        .badge-pending {
            display: inline-block;
            background-color: #fff1f2;
            color: #e11d48;
            border: 1px solid #fecdd3;
            padding: 1.5px 5px;
            border-radius: 2px;
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    <!-- 1. STORE HEADER -->
    <table class="header-table">
        <tr>
            <td style="width: 70%; vertical-align: middle;">
                <table style="width: 100%;">
                    <tr>
                        @if(!empty($logoBase64))
                            <td style="width: 45px; vertical-align: middle;">
                                <img src="{{ $logoBase64 }}" style="max-height: 38px; max-width: 42px; display: block;" alt="Logo">
                            </td>
                        @endif
                        <td style="vertical-align: middle;">
                            <h1 class="store-name">{{ $store->store_name ?? 'CorevisysPOS' }}</h1>
                            <div class="store-sub">{{ $sale->warehouse->warehouse_name ?? 'Main Warehouse' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 30%; vertical-align: middle; text-align: right;">
                <div class="doc-title">INVOICE PAD</div>
            </td>
        </tr>
    </table>

    <!-- 2. STORE INFO & INVOICE META STRIP -->
    <table class="info-table">
        <tr>
            <td class="info-col-left">
                <div style="font-weight: 700; text-transform: uppercase;">{{ $store->address ?? '' }}</div>
                @if($store && ($store->store_website ?? $store->website))
                    <div style="color: #2563eb; font-weight: 700; font-size: 6.5pt; margin-top: 1px;">
                        {{ str_replace(['http://', 'https://'], '', $store->store_website ?? $store->website) }}
                    </div>
                @endif
                <div style="margin-top: 2px; font-size: 6.5pt;">
                    <span class="micro-label">PH:</span> <strong style="color: #1e293b;">{{ $store->mobile ?? '' }}</strong>
                    @if($store && $store->email)
                        <span style="color: #cbd5e1; margin: 0 3px;">|</span>
                        <span class="micro-label">EM:</span> <strong style="color: #1e293b;">{{ $store->email }}</strong>
                    @endif
                </div>
                @if($store && ($store->gst_no || $store->vat_no))
                    <div style="margin-top: 1px; font-size: 6pt;">
                        @if($store->gst_no) <span>GST: <strong>{{ $store->gst_no }}</strong></span> @endif
                        @if($store->gst_no && $store->vat_no) <span style="color: #cbd5e1; margin: 0 3px;">|</span> @endif
                        @if($store->vat_no) <span>VAT: <strong>{{ $store->vat_no }}</strong></span> @endif
                    </div>
                @endif
            </td>
            <td class="info-col-right">
                <table style="width: 100%; text-align: right;">
                    <tr>
                        <td class="micro-label" style="text-align: right; padding-right: 4px;">Invoice No:</td>
                        <td class="micro-val" style="text-align: right;">#{{ $sale->sales_code }}</td>
                    </tr>
                    <tr>
                        <td class="micro-label" style="text-align: right; padding-right: 4px;">Date:</td>
                        <td class="micro-val" style="text-align: right;">{{ date('d/m/Y', strtotime($sale->sales_date)) }}</td>
                    </tr>
                    <tr>
                        <td class="micro-label" style="text-align: right; padding-right: 4px;">Warehouse:</td>
                        <td class="micro-val" style="text-align: right;">{{ $sale->warehouse->warehouse_name ?? 'Main Warehouse' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 3. BILLED TO & SETTLEMENT INFO -->
    <div class="recipient-box">
        <table style="width: 100%;">
            <tr>
                <td style="width: 65%; vertical-align: top; border-right: 1px solid #e2e8f0; padding-right: 8px;">
                    <div class="micro-label" style="margin-bottom: 2px;">Billed To / Recipient</div>
                    <div style="font-size: 9pt; font-weight: 800; text-transform: uppercase; color: #0f172a;">
                        {{ $sale->customer?->customer_name ?? 'Walk-in Customer' }}
                    </div>
                    @if($sale->customer?->address)
                        <div style="font-size: 6.5pt; color: #64748b; text-transform: uppercase; margin-top: 1px;">
                            {{ $sale->customer->address }}
                        </div>
                    @endif
                    @if($sale->customer?->mobile)
                        <div style="font-size: 7pt; font-weight: 800; color: #2563eb; margin-top: 2px;">
                            MOB: {{ $sale->customer->mobile }}
                        </div>
                    @endif
                </td>
                <td style="width: 35%; vertical-align: top; padding-left: 8px;">
                    <div class="micro-label" style="margin-bottom: 3px;">Settlement Info</div>
                    <table style="width: 100%;">
                        <tr>
                            <td style="font-size: 6.5pt; font-weight: 700; color: #64748b;">STATUS:</td>
                            <td style="text-align: right;">
                                @if($totalPayable <= $totalPaid)
                                    <span class="badge-settled">Settled</span>
                                @else
                                    <span class="badge-pending">Pending</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 6.5pt; font-weight: 700; color: #64748b; padding-top: 2px;">METHOD:</td>
                            <td style="text-align: right; font-size: 7pt; font-weight: 800; text-transform: uppercase; padding-top: 2px;">
                                {{ strtoupper($sale->payments->first()->payment_type ?? 'CASH') }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- 4. PRODUCT LINE ITEMS TABLE -->
    <table class="product-table">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">SL</th>
                <th style="text-align: left;">Item Description / Specifications</th>
                <th style="width: 65px; text-align: right;">Unit Price</th>
                <th style="width: 35px; text-align: center;">Qty</th>
                <th style="width: 75px; text-align: right;">Ext. Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $index => $item)
                <tr>
                    <td style="text-align: center; font-weight: 700; color: #94a3b8;">{{ $index + 1 }}</td>
                    <td>
                        <div style="font-weight: 800; color: #0f172a;">{{ $item->item->item_name }}</div>
                        @if($item->item->sku)
                            <div style="font-size: 5.5pt; font-weight: 700; color: #2563eb; letter-spacing: 0.05em; margin-top: 1px;">
                                SKU: {{ $item->item->sku }}
                            </div>
                        @endif
                        @if($sale->serials->where('item_id', $item->item_id)->count() > 0)
                            <div style="font-size: 6pt; font-weight: 700; color: #059669; margin-top: 1px;">
                                SN: {{ $sale->serials->where('item_id', $item->item_id)->pluck('serial_number')->implode(', ') }}
                            </div>
                        @endif
                        @if($item->item_details)
                            <div style="font-size: 6pt; color: #64748b; font-style: italic; margin-top: 1px;">
                                {{ $item->item_details }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align: right; color: #475569;">{{ format_currency($item->price_per_unit, false) }}</td>
                    <td style="text-align: center; font-weight: 800; color: #0f172a;">{{ format_quantity($item->sales_qty) }}</td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a;">{{ format_currency($item->total_cost, false) }}</td>
                </tr>
            @endforeach
            {{-- Blank pad rows to fill minimum 4-row height (matches screen view) --}}
            @for($i = count($sale->items); $i < 4; $i++)
                <tr style="opacity: 0.08;">
                    <td style="padding: 10px 6px;">&nbsp;</td>
                    <td style="padding: 10px 6px;">&nbsp;</td>
                    <td style="padding: 10px 6px;">&nbsp;</td>
                    <td style="padding: 10px 6px;">&nbsp;</td>
                    <td style="padding: 10px 6px;">&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <!-- 5. EMI FINANCIAL SUMMARY (IF APPLICABLE) -->
    @if($sale->emi)
        <div class="emi-box">
            <table style="width: 100%; margin-bottom: 4px; border-bottom: 1px solid #f1f5f9; padding-bottom: 3px;">
                <tr>
                    <td style="font-size: 7.5pt; font-weight: 900; text-transform: uppercase; color: #0f172a;">
                        EMI Financial Summary
                    </td>
                    <td style="text-align: right; font-size: 6.5pt; font-weight: 800; color: #2563eb; text-transform: uppercase;">
                        {{ $sale->emi->duration_months }} Months Plan
                    </td>
                </tr>
            </table>
            <table style="width: 100%; text-align: center;">
                <tr>
                    <td style="width: 20%; text-align: left;">
                        <div class="micro-label" style="font-size: 5pt;">Total Payable</div>
                        <div class="micro-val" style="font-size: 7pt;">{{ format_currency($sale->emi->total_payable) }}</div>
                    </td>
                    <td style="width: 20%;">
                        <div class="micro-label" style="font-size: 5pt;">Total Collected</div>
                        <div class="micro-val" style="font-size: 7pt; color: #059669;">{{ format_currency($totalPaid) }}</div>
                    </td>
                    <td style="width: 20%; border-left: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9;">
                        <div class="micro-label" style="font-size: 5pt;">Monthly Inst.</div>
                        <div class="micro-val" style="font-size: 7pt;">{{ format_currency($sale->emi->monthly_installment) }}</div>
                    </td>
                    <td style="width: 20%;">
                        <div class="micro-label" style="font-size: 5pt;">Initial Pay</div>
                        <div class="micro-val" style="font-size: 7pt;">{{ format_currency($sale->paid_amount) }}</div>
                    </td>
                    <td style="width: 20%; text-align: right;">
                        <div class="micro-label" style="font-size: 5pt;">Remaining Due</div>
                        <div class="micro-val" style="font-size: 7pt; color: #e11d48;">
                            {{ format_currency($sale->emi->total_payable - $sale->emi->schedule->sum('paid_amount')) }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <!-- 6. SUMMARY, AMOUNT IN WORDS & TOTALS BLOCK -->
    <table class="summary-table">
        <tr>
            <!-- Left Side: Amount In Words & Signatures -->
            <td class="summary-left">
                @if($store && $store->number_to_words)
                    <div class="micro-label">Amount In Words (Invoicing Currency)</div>
                    <div class="words-box">
                        {{ $amount_in_words }}
                    </div>
                @endif

                @if($store && $store->t_and_c_status && $store->invoice_terms)
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 2px; padding: 4px 6px; font-size: 5.5pt; color: #475569; margin-top: 5px; margin-bottom: 4px;">
                        <span style="font-weight: 800; text-transform: uppercase; color: #0f172a; display: block; margin-bottom: 2px;">Terms & Conditions:</span>
                        <div style="line-height: 1.3;">{!! nl2br(e($store->invoice_terms)) !!}</div>
                    </div>
                @endif

                <table class="sig-table">
                    <tr>
                        <td style="width: 45%; padding-right: 10px;">
                            <div style="height: 48px;">&nbsp;</div>
                            <div class="sig-line">Client Acknowledgement</div>
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 45%; padding-left: 10px;">
                            <div style="height: 48px;">&nbsp;</div>
                            <div class="sig-line">Authorized Signatory</div>
                        </td>
                    </tr>
                </table>
            </td>

            <!-- Right Side: Totals Card -->
            <td class="summary-right">
                <div class="totals-card">
                    <table style="width: 100%;">
                        <tr>
                            <td class="totals-row" style="text-align: left;">SUB TOTAL:</td>
                            <td class="totals-row" style="text-align: right; color: #0f172a;">{{ format_currency($sale->subtotal, false) }}</td>
                        </tr>
                        <tr>
                            <td class="totals-row" style="text-align: left;">TAX CHARGE:</td>
                            <td class="totals-row" style="text-align: right; color: #0f172a;">+{{ format_currency($sale->tax_amt, false) }}</td>
                        </tr>
                        <tr>
                            <td class="totals-row" style="text-align: left; color: #f43f5e;">DISCOUNT:</td>
                            <td class="totals-row" style="text-align: right; color: #f43f5e;">-{{ format_currency(($sale->tot_discount_to_all_amt ?? 0) + ($sale->coupon_amt ?? 0), false) }}</td>
                        </tr>
                        @if($sale->emi)
                            <tr>
                                <td class="totals-row" style="text-align: left; color: #2563eb;">PROCESSING FEE:</td>
                                <td class="totals-row" style="text-align: right; color: #2563eb;">+{{ format_currency($sale->emi->processing_fee, false) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="grand-total-row" style="text-align: left; font-size: 7.5pt;">NET PAYABLE:</td>
                            <td class="grand-total-row" style="text-align: right;">
                                {{ format_currency($totalPayable) }}
                            </td>
                        </tr>
                    </table>

                    <div class="settlement-card">
                        <table style="width: 100%;">
                            <tr>
                                <td style="font-size: 6.5pt; font-weight: 700; color: #64748b; text-transform: uppercase;">TOTAL PAID:</td>
                                <td style="text-align: right; font-size: 7pt; font-weight: 800; color: #059669;">
                                    {{ format_currency($totalPaid, false) }}
                                </td>
                            </tr>
                            @if($store && $store->change_return && $totalPaid > $totalPayable)
                                <tr>
                                    <td style="font-size: 6.5pt; font-weight: 700; color: #059669; text-transform: uppercase;">CHANGE RETURN:</td>
                                    <td style="text-align: right; font-size: 7pt; font-weight: 800; color: #059669;">
                                        {{ format_currency($totalPaid - $totalPayable, false) }}
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td style="font-size: 7pt; font-weight: 800; color: #475569; text-transform: uppercase; border-top: 1px solid #e2e8f0; padding-top: 2px;">CURRENT DUE:</td>
                                <td style="text-align: right; font-size: 7.5pt; font-weight: 900; color: #e11d48; border-top: 1px solid #e2e8f0; padding-top: 2px;">
                                    {{ format_currency($dueBalance, false) }}
                                </td>
                            </tr>
                            @if($store && $store->previous_balance_bit && ($previousBalance > 0 || $sale->customer_id))
                                <tr>
                                    <td style="font-size: 6.5pt; font-weight: 700; color: #b45309; text-transform: uppercase; border-top: 1px solid #e2e8f0; padding-top: 2px;">PREVIOUS DUE:</td>
                                    <td style="text-align: right; font-size: 7pt; font-weight: 800; color: #b45309; border-top: 1px solid #e2e8f0; padding-top: 2px;">
                                        {{ format_currency($previousBalance, false) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 7pt; font-weight: 800; color: #0f172a; text-transform: uppercase; border-top: 1px solid #e2e8f0; padding-top: 2px;">TOTAL DUE:</td>
                                    <td style="text-align: right; font-size: 7.5pt; font-weight: 900; color: #e11d48; border-top: 1px solid #e2e8f0; padding-top: 2px;">
                                        {{ format_currency($totalDueWithPrevious, false) }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    @if($store && $store->sales_invoice_footer_text)
        <div style="text-align: center; font-size: 6pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #334155; margin-top: 4px; margin-bottom: 3px; font-style: italic;">
            {{ $store->sales_invoice_footer_text }}
        </div>
    @endif

    <!-- 7. BOTTOM NANO BAR (2 clean left-aligned rows, no emojis) -->
    <table class="footer-bar">
        <tr>
            <td style="text-align: left; font-style: italic; padding-bottom: 2px;">
                This document is valid without physical seal if generated electronically.
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">
                Memo ID: {{ $sale->id }} &nbsp;|&nbsp; User: {{ $sale->user->name ?? 'Admin' }}
                &nbsp;&nbsp;
                <span style="display: inline-block; background: #0f172a; color: #fff; font-size: 5pt; font-weight: 900; text-transform: uppercase; letter-spacing: 0.06em; padding: 1.5px 5px; border-radius: 2px;">
                    Powered by CorevisysPOS
                </span>
            </td>
        </tr>
    </table>

</body>
</html>
