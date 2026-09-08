<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $sale->sales_code }}</title>
    @vite('resources/css/app.css') 
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-print-color-adjust: exact;
            background-color: #f1f5f9;
        }

        @page {
            size: auto;
            margin: 0;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 9pt !important;
                line-height: 1.3 !important;
            }

            .invoice-container {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 6mm !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
            }

            .micro-label {
                font-size: 7pt !important;
            }

            .micro-data {
                font-size: 8.5pt !important;
            }

            .nano-text {
                font-size: 6pt !important;
            }

            .table-compact th {
                font-size: 7.5pt !important;
                padding: 6px 8px !important;
            }

            .table-compact td {
                font-size: 8.5pt !important;
                padding: 6px 8px !important;
            }
        }

        .invoice-container {
            margin: 1rem auto;
            background: white;
            padding: 10mm;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
        }

        .invoice-container.size-a4 {
            max-width: 210mm;
            min-height: 297mm;
        }

        .invoice-container.size-letter {
            max-width: 215.9mm;
            min-height: 279.4mm;
        }

        .micro-label {
            font-size: 6px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
        }

        .micro-data {
            font-size: 8px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
        }

        .nano-text {
            font-size: 5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table-compact th {
            font-size: 6.5px;
            padding: 4px 6px;
        }

        .table-compact td {
            font-size: 8px;
            padding: 4px 6px;
            border-right: 1px solid #f1f5f9;
        }

        .table-compact td:last-child {
            border-right: none;
        }
    </style>
</head>

<body class="p-0 sm:p-2" x-data="invoiceViewer('{{ $paperSize ?? 'a4' }}', {{ $sale->id }}, {{ ($autoPrint ?? false) ? 'true' : 'false' }})">

    <!-- Action Bar (Sticky, Screen Only) -->
    <div class="max-w-[215.9mm] mx-auto no-print mb-2 flex flex-wrap justify-between items-center bg-white p-2 rounded-xl border border-slate-200 shadow-sm gap-2">
        <div class="flex items-center gap-3">
            <!-- Smart Navigation Button (Closes tab if popup, goes back if history exists, else redirects to sales list) -->
            <button type="button" @click="handleClose()"
                class="text-[9px] font-black text-slate-600 hover:text-rose-600 uppercase tracking-widest px-2.5 py-1.5 rounded-lg hover:bg-rose-50 border border-slate-200 hover:border-rose-200 flex items-center gap-1.5 transition-all cursor-pointer">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span x-text="isPopup ? 'Close' : 'Back'">Back</span>
            </button>

            <!-- Paper Size Selector -->
            <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-slate-200 text-[9px] font-bold">
                <span class="text-[8px] font-black text-slate-400 uppercase tracking-wider px-1.5">Size:</span>
                <button type="button" @click="setPaper('a4')"
                    :class="paper === 'a4' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-500 hover:text-slate-700'"
                    class="px-2.5 py-1 rounded-md text-[9px] uppercase tracking-wider transition-all cursor-pointer">
                    A4
                </button>
                <button type="button" @click="setPaper('letter')"
                    :class="paper === 'letter' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-500 hover:text-slate-700'"
                    class="px-2.5 py-1 rounded-md text-[9px] uppercase tracking-wider transition-all cursor-pointer">
                    Letter (US)
                </button>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Print Now (Opens DomPDF vector stream in PDF viewer for full-page A4/Letter print) -->
            <button type="button" @click="printNow()"
                class="text-[9px] bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-lg font-black uppercase tracking-wider shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print Now (<span x-text="paper.toUpperCase()"></span>)</span>
            </button>

            <!-- Download PDF (Direct file download handled by server attachment header) -->
            <a :href="downloadUrl" href="{{ url('sales/invoice/' . $sale->id . '?mode=download&paper_size=' . ($paperSize ?? 'a4')) }}"
                class="text-[9px] bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-1.5 rounded-lg font-black uppercase tracking-wider shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>Download PDF</span>
            </a>
        </div>
    </div>

    <!-- Invoice Pad Container -->
    <div :class="'invoice-container size-' + paper">
        <!-- Pad Top Border -->
        <div class="flex justify-between items-center border-b border-slate-900 pb-2 mb-4">
            <div class="flex items-center gap-2">
                @if($store && $store->store_logo)
                    <img src="{{ asset('storage/' . $store->store_logo) }}" class="h-8 w-auto" alt="Logo">
                @else
                    <div class="w-8 h-8 bg-slate-900 rounded flex items-center justify-center text-white text-lg font-black">
                        {{ substr($store->store_name ?? 'C', 0, 1) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-[12px] font-black text-slate-900 uppercase leading-none">{{ $store->store_name ?? 'CorevisysPOS' }}</h1>
                    <p class="text-[5px] text-slate-400 font-black uppercase tracking-[0.3em] mt-0.5 whitespace-nowrap">
                        {{ $sale->warehouse->warehouse_name ?? 'Main Warehouse' }}
                    </p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-[14px] font-black text-slate-900 italic uppercase leading-none opacity-20">Invoice Pad</h2>
            </div>
        </div>

        <!-- Store Info Strip -->
        <div class="flex justify-between items-start mb-6 pb-4 border-b border-slate-50">
            <div class="max-w-[150px]">
                <p class="text-[7px] text-slate-500 font-bold uppercase leading-tight">{{ $store->address ?? '' }}</p>
                @if($store && ($store->store_website ?? $store->website))
                    <p class="text-[6px] text-blue-500 font-black mt-1 uppercase tracking-tighter">
                        {{ str_replace(['http://', 'https://'], '', $store->store_website ?? $store->website) }}
                    </p>
                @endif
                <div class="flex items-center gap-3 mt-1.5 py-1 border-y border-slate-50">
                    <div class="flex items-center gap-1">
                        <span class="micro-label">PH:</span>
                        <span class="text-[7px] font-black text-slate-800 tracking-tighter">{{ $store->mobile ?? '' }}</span>
                    </div>
                    <div class="w-px h-2 bg-slate-200"></div>
                    <div class="flex items-center gap-1">
                        <span class="micro-label">EM:</span>
                        <span class="text-[7px] font-black text-slate-800 tracking-tighter italic lowercase">{{ $store->email ?? '' }}</span>
                    </div>
                </div>
                @if($store && ($store->gst_no || $store->vat_no))
                    <div class="flex gap-3 mt-1 opacity-80">
                        @if($store->gst_no)
                            <div class="flex items-center">
                                <span class="nano-text font-black text-slate-400">GST:</span>
                                <span class="text-[5.5px] font-black text-slate-600 ml-0.5">{{ $store->gst_no }}</span>
                            </div>
                        @endif
                        @if($store->vat_no)
                            <div class="flex items-center">
                                <span class="nano-text font-black text-slate-400">VAT:</span>
                                <span class="text-[5.5px] font-black text-slate-600 ml-0.5">{{ $store->vat_no }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-right">
                <span class="micro-label">Invoice No:</span>
                <span class="micro-data">#{{ $sale->sales_code }}</span>
                <span class="micro-label">Date:</span>
                <span class="micro-data">{{ date('d/m/Y', strtotime($sale->sales_date)) }}</span>
                <span class="micro-label">Warehouse:</span>
                <span class="micro-data">{{ $sale->warehouse->warehouse_name ?? 'Main Warehouse' }}</span>
            </div>
        </div>

        <!-- Recipient Block -->
        <div class="grid grid-cols-12 mb-6 bg-slate-50 border border-slate-100 p-3 rounded">
            <div class="col-span-8 border-r border-slate-200 pr-4">
                <span class="micro-label block mb-2 opacity-50">Billed To / Recipient</span>
                <h3 class="text-[10px] font-black text-slate-900 uppercase mb-1">
                    {{ $sale->customer?->customer_name ?? 'Walk-in Customer' }}
                </h3>
                <p class="text-[7px] text-slate-500 font-bold uppercase leading-tight">
                    {{ $sale->customer?->address ?? '' }}
                </p>
                <p class="text-[8px] font-black text-blue-600 mt-2">MOB: {{ $sale->customer?->mobile ?? '' }}</p>
            </div>
            <div class="col-span-4 pl-4 flex flex-col justify-center">
                <span class="micro-label block mb-2 opacity-50">Settlement Info</span>
                <div class="flex justify-between items-center mb-1">
                    <span class="text-[7px] font-bold text-slate-400">STATUS:</span>
                    @if($totalPayable <= $totalPaid)
                        <span class="text-[7px] font-black text-emerald-600 uppercase border border-emerald-100 bg-emerald-50 px-1 rounded-sm tracking-tighter">Settled</span>
                    @else
                        <span class="text-[7px] font-black text-rose-600 uppercase border border-rose-100 bg-rose-50 px-1 rounded-sm tracking-tighter">Pending</span>
                    @endif
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[7px] font-bold text-slate-400">METHOD:</span>
                    <span class="text-[7px] font-black text-slate-800 uppercase">{{ strtoupper($sale->payments->first()->payment_type ?? 'CASH') }}</span>
                </div>
            </div>
        </div>

        <!-- Main Product Table -->
        <div class="border border-slate-900 rounded-sm overflow-hidden mb-6">
            <table class="w-full table-compact border-collapse">
                <thead>
                    <tr class="bg-white text-slate-900 border-b-2 border-slate-900">
                        <th class="w-8">SL</th>
                        <th class="text-left">ITEM DESCRIPTION / SPECIFICATIONS</th>
                        <th class="w-16 text-right">UNIT PRICE</th>
                        <th class="w-12 text-center">QTY</th>
                        <th class="w-20 text-right">EXT. TOTAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($sale->items as $index => $item)
                        <tr>
                            <td class="text-center font-bold text-slate-400">{{ $index + 1 }}</td>
                            <td class="py-2">
                                <div class="micro-data leading-none">{{ $item->item->item_name }}</div>
                                @if($item->item->sku)
                                    <div class="text-[5.5px] font-black text-blue-500 mt-0.5 tracking-widest opacity-70">
                                        SKU: {{ $item->item->sku }}
                                    </div>
                                @endif
                                @if($sale->serials->where('item_id', $item->item_id)->count() > 0)
                                    <div class="text-[6px] text-emerald-600 font-bold mt-1 tracking-tighter uppercase">
                                        SN: {{ $sale->serials->where('item_id', $item->item_id)->pluck('serial_number')->implode(', ') }}
                                    </div>
                                @endif
                                @if($item->item_details)
                                    <div class="text-[6px] text-slate-400 mt-0.5 italic leading-tight">
                                        {{ $item->item_details }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-right font-medium text-slate-600">{{ format_currency($item->price_per_unit, false) }}</td>
                            <td class="text-center font-black text-slate-900">{{ format_quantity($item->sales_qty) }}</td>
                            <td class="text-right font-black text-slate-900">{{ format_currency($item->total_cost, false) }}</td>
                        </tr>
                    @endforeach

                    <!-- Fill Empty Space with Pad Design if short -->
                    @for($i = count($sale->items); $i < 4; $i++)
                        <tr class="opacity-10 border-none">
                            <td class="py-4">&nbsp;</td>
                            <td class="py-4">&nbsp;</td>
                            <td class="py-4">&nbsp;</td>
                            <td class="py-4">&nbsp;</td>
                            <td class="py-4">&nbsp;</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        @if($sale->emi)
            <!-- EMI PERFORMANCE SUMMARY -->
            <div class="grid grid-cols-12 gap-4 mb-6">
                <div class="col-span-12">
                    <div class="bg-white border border-slate-900/20 rounded-sm p-3 shadow-sm">
                        <div class="flex justify-between items-center mb-3 pb-2 border-b border-slate-900/5">
                            <span class="text-[8px] font-black text-slate-900 uppercase tracking-widest">EMI Financial Summary</span>
                            <span class="text-[7px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full uppercase tracking-tighter">
                                {{ $sale->emi->duration_months }} Months Plan
                            </span>
                        </div>
                        <div class="grid grid-cols-5 gap-4">
                            <div class="flex flex-col">
                                <span class="micro-label !text-[5px]">Total Payable</span>
                                <span class="micro-data">{{ format_currency($sale->emi->total_payable) }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="micro-label !text-[5px]">Total Collected</span>
                                <span class="micro-data text-emerald-600">{{ format_currency($sale->payments->sum('payment')) }}</span>
                            </div>
                            <div class="flex flex-col border-x border-slate-900/5 px-4">
                                <span class="micro-label !text-[5px]">Monthly Installment</span>
                                <span class="micro-data">{{ format_currency($sale->emi->monthly_installment) }}</span>
                            </div>
                            <div class="flex flex-col px-4">
                                <span class="micro-label !text-[5px]">Initial Pay</span>
                                <span class="micro-data">{{ format_currency($sale->paid_amount) }}</span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="micro-label !text-[5px]">Remaining Due</span>
                                <span class="micro-data text-rose-600">{{ format_currency($sale->emi->total_payable - $sale->emi->schedule->sum('paid_amount')) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Summary & Footer Strip -->
        <div class="grid grid-cols-12 gap-8 mb-6">
            <div class="col-span-7 flex flex-col justify-between">
                @if($store && $store->number_to_words)
                    <div>
                        <span class="micro-label block mb-1 opacity-50">Amount In Words (Invoicing Currency)</span>
                        <div class="bg-slate-50 p-2 rounded border-l-2 border-slate-300 italic">
                            <p class="text-[7.5px] font-black text-slate-600 uppercase tracking-tighter">
                                {{ $amount_in_words }}
                            </p>
                        </div>
                    </div>
                @else
                    <div></div>
                @endif
                @if($store && $store->t_and_c_status && $store->invoice_terms)
                    <div class="mt-3 p-2 bg-slate-50 border border-slate-100 rounded text-[6.5px]">
                        <span class="micro-label block mb-0.5 opacity-60 font-black">Terms & Conditions:</span>
                        <p class="text-slate-600 leading-tight whitespace-pre-line">{{ $store->invoice_terms }}</p>
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <div class="h-12"></div>
                        <div class="border-t border-slate-900 pt-1 text-center">
                            <span class="text-[5px] font-black text-slate-400 uppercase tracking-widest block">Client Acknowledgement</span>
                        </div>
                    </div>
                    <div>
                        <div class="h-12"></div>
                        <div class="border-t border-slate-900 pt-1 text-center">
                            <span class="text-[5px] font-black text-slate-400 uppercase tracking-widest block">Authorized Signatory</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-5 bg-white border border-slate-900 p-3 rounded-sm shadow-sm">
                <div class="space-y-1 mb-3">
                    <div class="flex justify-between items-center text-[7px] font-bold text-slate-400 uppercase tracking-tighter">
                        <span>SUB TOTAL:</span>
                        <span class="text-slate-700 font-black">{{ format_currency($sale->subtotal, false) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-[7px] font-bold text-slate-400 uppercase tracking-tighter">
                        <span>TAX CHARGE:</span>
                        <span class="text-slate-700 font-black">+{{ format_currency($sale->tax_amt, false) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-[7px] font-bold text-rose-500 uppercase tracking-tighter">
                        <span>DISCOUNT:</span>
                        <span class="font-black">-{{ format_currency(($sale->tot_discount_to_all_amt ?? 0) + ($sale->coupon_amt ?? 0), false) }}</span>
                    </div>
                    @if($sale->emi)
                        <div class="flex justify-between items-center text-[7px] font-bold text-blue-600 uppercase tracking-tighter">
                            <span>PROCESSING FEE:</span>
                            <span class="font-black">+{{ format_currency($sale->emi->processing_fee, false) }}</span>
                        </div>
                    @endif
                </div>
                <div class="flex justify-between items-end border-t border-slate-100 pt-2 mb-3">
                    <span class="text-[6px] font-black text-slate-400 uppercase tracking-[0.2em] mb-0.5">NET PAYABLE:</span>
                    <span class="text-[11px] font-extrabold tracking-widest text-slate-900 italic">
                        {{ format_currency($totalPayable) }}
                    </span>
                </div>
                <div class="bg-slate-50 p-2 rounded-sm space-y-1.5 border border-slate-100">
                    <div class="flex justify-between items-center text-[6.5px] font-black">
                        <span class="text-slate-400 uppercase">TOTAL PAID:</span>
                        <span class="text-emerald-600">{{ format_currency($totalPaid, false) }}</span>
                    </div>
                    @if($store && $store->change_return && $totalPaid > $totalPayable)
                        <div class="flex justify-between items-center text-[6.5px] font-black text-emerald-600">
                            <span class="uppercase">CHANGE RETURN:</span>
                            <span class="font-bold">{{ format_currency($totalPaid - $totalPayable, false) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between items-center text-[7.5px] font-black border-t border-slate-200 pt-1.5">
                        <span class="text-slate-500 uppercase italic">CURRENT DUE:</span>
                        <span class="text-rose-600 font-extrabold uppercase">{{ format_currency($dueBalance, false) }}</span>
                    </div>
                    @if($store && $store->previous_balance_bit && ($previousBalance > 0 || $sale->customer_id))
                        <div class="flex justify-between items-center text-[6.5px] font-black border-t border-slate-200 pt-1 text-amber-700">
                            <span class="uppercase">PREVIOUS DUE:</span>
                            <span class="font-bold">{{ format_currency($previousBalance, false) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-[7.5px] font-black border-t border-slate-300 pt-1">
                            <span class="text-slate-700 uppercase italic">TOTAL DUE:</span>
                            <span class="text-rose-600 font-black uppercase">{{ format_currency($totalDueWithPrevious, false) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($store && $store->sales_invoice_footer_text)
            <div class="text-center text-[7px] font-black text-slate-700 uppercase tracking-widest my-2 italic">
                {{ $store->sales_invoice_footer_text }}
            </div>
        @endif

        <!-- Bottom Nano Bar -->
        <div class="mt-auto border-t border-slate-100 pt-2 flex flex-col gap-1 opacity-70">
            <div class="text-[5.5px] font-bold text-slate-500 uppercase italic">
                This document is valid without physical seal if generated electronically.
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[5.5px] font-black text-slate-700 uppercase tracking-widest">Memo ID: {{ $sale->id }}</span>
                <span class="text-[5.5px] font-black text-slate-400">|</span>
                <span class="text-[5.5px] font-black text-slate-700 uppercase tracking-widest">User: {{ $sale->user->name ?? 'Admin' }}</span>
                <span class="inline-flex items-center bg-slate-900 text-white text-[5px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded-xs">
                    Powered by CorevisysPOS
                </span>
            </div>
        </div>
    </div>

    <script>
        function invoiceViewer(initialPaper, saleId, autoPrint) {
            return {
                paper: initialPaper,
                saleId: saleId,
                isPopup: Boolean(window.opener),
                autoPrint: Boolean(autoPrint),
                init() {
                    // "Save & Print" from the Add Sale page: reuse the existing print
                    // mechanism (opens the DomPDF stream in a new tab for full-page print).
                    if (this.autoPrint) {
                        this.$nextTick(() => this.printNow());
                    }
                },

                setPaper(size) {
                    this.paper = size;
                    const url = new URL(window.location);
                    url.searchParams.set('paper_size', size);
                    window.history.replaceState({}, '', url);
                },

                get downloadUrl() {
                    return `/sales/invoice/${this.saleId}?mode=download&paper_size=${this.paper}`;
                },

                printNow() {
                    const streamUrl = `/sales/invoice/${this.saleId}?mode=stream&paper_size=${this.paper}`;
                    window.open(streamUrl, '_blank');
                },

                handleClose() {
                    if (this.isPopup) {
                        window.close();
                        return;
                    }
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = '/sales';
                    }
                }
            };
        }
    </script>
</body>

</html>