<x-app-layout title="Print Barcode">
    <div class="print:p-0 print:pb-0">
        <!-- HEADER (Hidden in Print) -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6 print:hidden">
            <div>
                <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-white">Print Barcodes</h1>
                <p class="text-[10px] text-slate-400 font-medium uppercase tracking-widest">
                    Purchase: {{ $purchase->purchase_code }}
                </p>
            </div>
            
            <div class="flex gap-3">
                 <button onclick="window.print()" class="px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 shadow-lg shadow-primary-200 dark:shadow-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 012-2H5a2 2 0 012 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Labels
                </button>
                <a href="{{ url()->previous() }}" class="px-6 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">
                    Back
                </a>
            </div>
        </div>

        <!-- BARCODE GRID -->
        <div class="bg-white p-8 rounded-3xl shadow-sm print:shadow-none print:p-0 print:bg-transparent">
            <div class="grid grid-cols-5 gap-4 print:gap-2 print:block">
                @foreach($purchase->items as $item)
                    @php 
                        $qty = (int)$item->purchase_qty; 
                        $storeName = auth()->user()->store->store_name ?? 'COREVISYS POS';
                        $isSerialized = $item->serials && $item->serials->count() > 0;
                        $loops = $isSerialized ? $item->serials : range(1, $qty);
                    @endphp

                    @foreach($loops as $loopItem)
                        @php
                            if ($isSerialized) {
                                $barcodeValue = $loopItem->serial_number; // It's a serial object
                            } else {
                                $barcodeValue = $item->item->custom_barcode ? $item->item->custom_barcode : $item->item->item_code;
                            }
                        @endphp
                        
                        @if($barcodeValue)
                            <div class="barcode-item border border-dotted border-slate-300 p-2 flex flex-col items-center justify-center text-center bg-white page-break-inside-avoid print:float-left print:m-1 print:border-none print:p-0 w-[200px] min-h-[120px]">
                                <!-- Store Name -->
                                <h4 class="text-[12px] font-bold text-black uppercase tracking-tight leading-tight w-full mb-0.5 font-[sans-serif]">{{ $storeName }}</h4>
                                
                                <!-- Item Name -->
                                <p class="text-[11px] font-medium text-black leading-tight line-clamp-2 w-full mb-0.5 font-[sans-serif]">
                                    {{ $item->item->item_name }}
                                </p>
                                
                                <!-- Price -->
                                    Price: {{ format_currency($item->item->sales_price) }}

                                <!-- Barcode SVG Container -->
                                <div class="w-full flex items-center justify-center overflow-hidden mb-1">
                                     {!! $generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128, 1.5, 40) !!}
                                </div>
                                <!-- Explicit Text Fallback/Redundancy -->
                                <p class="text-[10px] font-bold text-black tracking-[0.2em] font-[monospace]">{{ $barcodeValue }}</p>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .page-break-inside-avoid {
            page-break-inside: avoid;
        }
        @media print {
            @page {
                size: auto;
                margin: 0mm;
            }
            body {
                margin: 10mm;
            }
            .barcode-item {
                /* Force explicit dimensions for sticky labels if needed, or keeping float for continuous rolls */
                page-break-inside: avoid;
            }
        }
    </style>
</x-app-layout>
