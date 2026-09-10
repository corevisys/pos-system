<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Providers\AppServiceProvider;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleInvoiceController extends Controller
{
    /**
     * Display or generate the sale invoice.
     *
     * @param Request $request
     * @param int $id Sale ID
     * @return \Illuminate\Http\Response|\Illuminate\View\View
     */
    public function show(Request $request, $id)
    {
        $sale = DbSale::with([
            'items.item',
            'customer.country',
            'customer.state',
            'payments',
            'user',
            'warehouse',
            'serials',
            'emi.schedule'
        ])->findOrFail($id);

        $paperSize = strtolower($request->query('paper_size', 'a4'));
        if (!in_array($paperSize, ['a4', 'letter'], true)) {
            $paperSize = 'a4';
        }

        $mode = strtolower($request->query('mode', 'view'));

        // "Save & Print" flow: the Add Sale page redirects with ?print=true so the
        // invoice view auto-triggers the existing printNow() mechanism on load.
        $autoPrint = filter_var($request->query('print', false), FILTER_VALIDATE_BOOLEAN);

        $data = $this->prepareInvoiceData($sale, $paperSize, $mode);
        $data['autoPrint'] = $autoPrint;

        // PDF Generation Modes
        if (in_array($mode, ['download', 'pdf', 'stream', 'print'], true)) {
            $pdf = Pdf::loadView('module.sales.invoice.pdf', $data);
            $pdf->setPaper($paperSize, 'portrait');

            $filename = 'Invoice-' . $sale->sales_code . '-' . strtoupper($paperSize) . '-' . date('Y-m-d') . '.pdf';

            // Download: Triggers file save dialog (Content-Disposition: attachment)
            if ($mode === 'download' || $mode === 'pdf') {
                return $pdf->download($filename);
            }

            // Stream / Print: Displays inline in browser PDF viewer (Content-Disposition: inline)
            return $pdf->stream($filename);
        }

        // Default: On-Screen Interactive Blade View
        return view('module.sales.invoice.view', $data);
    }

    /**
     * Prepare consistent data for view and PDF rendering.
     *
     * @param DbSale $sale
     * @param string $paperSize
     * @param string $mode
     * @return array
     */
    private function prepareInvoiceData(DbSale $sale, string $paperSize, string $mode): array
    {
        // Resolve the invoice header store from the SALE's store_id (not the
        // acting user), so an invoice always renders the store that owns it.
        // store_settings() memoizes the row under 's{id}', so the currency
        // symbol resolver below AND the layout's store-name binding reuse this
        // same row — a single request that renders an invoice therefore issues
        // exactly ONE db_store SELECT (see the exact-count guarantee test).
        // Falls back to the memoized default store only when the sale carries
        // no store_id (pre-multi-store rows).
        $invoiceStoreId = $sale->store_id ? (int) $sale->store_id : null;
        $store = function_exists('store_settings')
            ? store_settings(false, $invoiceStoreId)
            : ($invoiceStoreId ? DbStore::where('id', $invoiceStoreId)->first() : DbStore::first());
        if (!$store && $invoiceStoreId) {
            $store = DbStore::find($invoiceStoreId);
        }
        $invoiceStoreId = $store->id ?? $invoiceStoreId;

        // Resolve the active currency symbol via the single source of truth,
        // keyed to the sale's store so a multi-store deployment never renders
        // another store's currency on this invoice.
        // NOTE: db_store has no currency_code column — it has currency_id (FK to db_currency).
        // The old code read $store->currency_code which always returned null, causing every
        // invoice/PDF to silently display '$' regardless of the configured active currency.
        $currencySymbol = AppServiceProvider::resolveCurrencySymbol(false, $invoiceStoreId);

        // Store logo base64 encoder for reliable DomPDF rendering without local file URL issues
        $logoBase64 = null;
        if ($store && !empty($store->store_logo)) {
            $logoPath = public_path('storage/' . $store->store_logo);
            if (file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $imgData = file_get_contents($logoPath);
                if ($imgData !== false) {
                    $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
                }
            }
        }

        $totalPayable = (float) $sale->grand_total + (float) ($sale->emi->processing_fee ?? 0);
        $totalPaid = (float) $sale->payments->sum('payment');
        $dueBalance = $totalPayable - $totalPaid;

        // Customer Previous Ledger Balance (when previous_balance_bit is enabled)
        $previousBalance = 0;
        if ($store && !empty($store->previous_balance_bit) && $sale->customer_id) {
            $priorSales = DbSale::where('customer_id', $sale->customer_id)->where('id', '<', $sale->id)->sum('grand_total');
            $priorReturns = \App\Models\DbSalesReturn::where('customer_id', $sale->customer_id)->where('id', '<', $sale->id)->sum('grand_total');
            $priorPayments = \App\Models\DbSalePayment::where('customer_id', $sale->customer_id)->where('sales_id', '<', $sale->id)->sum('payment');
            $priorReturnPayments = \App\Models\DbSalesPaymentReturn::where('customer_id', $sale->customer_id)->sum('payment');
            $openingBalance = (float) ($sale->customer->opening_balance ?? 0);

            $calculatedPriorDue = $openingBalance + ($priorSales - $priorReturns) - ($priorPayments - $priorReturnPayments);
            $previousBalance = max(0, $calculatedPriorDue);
        }
        $totalDueWithPrevious = $dueBalance + $previousBalance;

        return [
            'sale' => $sale,
            'store' => $store,
            'paperSize' => $paperSize,
            'currencySymbol' => $currencySymbol,
            'logoBase64' => $logoBase64,
            'totalPayable' => $totalPayable,
            'totalPaid' => $totalPaid,
            'dueBalance' => $dueBalance,
            'previousBalance' => $previousBalance,
            'totalDueWithPrevious' => $totalDueWithPrevious,
            'amount_in_words' => $this->convertNumberToWords($totalPayable),
            'mode' => $mode
        ];
    }

    /**
     * Convert monetary amount to words (South Asian numbering: Taka and Paisa).
     *
     * @param float|int $number
     * @return string
     */
    private function convertNumberToWords($number): string
    {
        $number = round((float) $number, 2);
        if ($number <= 0) {
            return 'Zero Taka Only';
        }

        $integerPart = (int) floor($number);
        $decimalPart = (int) round(($number - $integerPart) * 100);

        $words = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
            80 => 'Eighty', 90 => 'Ninety'
        ];

        $units = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
        $numStr = (string) $integerPart;
        $len = strlen($numStr);
        $chunks = [];
        $i = 0;

        while ($i < $len) {
            $divider = ($i === 2) ? 10 : 100;
            $currentChunk = $integerPart % $divider;
            $integerPart = (int) floor($integerPart / $divider);
            $i += ($divider === 10) ? 1 : 2;

            if ($currentChunk > 0) {
                $counter = count($chunks);
                $unitLabel = $units[$counter] ?? '';
                $chunkWords = '';

                if ($currentChunk < 20) {
                    $chunkWords = $words[$currentChunk];
                } elseif ($currentChunk < 100) {
                    $tens = (int) (floor($currentChunk / 10) * 10);
                    $ones = $currentChunk % 10;
                    $chunkWords = $words[$tens] . ($ones > 0 ? ' ' . $words[$ones] : '');
                }

                $chunks[] = trim($chunkWords . ($unitLabel !== '' ? ' ' . $unitLabel : ''));
            } else {
                $chunks[] = null;
            }
        }

        $chunks = array_filter($chunks);
        $chunks = array_reverse($chunks);
        $takaString = implode(' ', $chunks);

        $paisaString = '';
        if ($decimalPart > 0) {
            if ($decimalPart < 20) {
                $paisaWords = $words[$decimalPart];
            } else {
                $tens = (int) (floor($decimalPart / 10) * 10);
                $ones = $decimalPart % 10;
                $paisaWords = $words[$tens] . ($ones > 0 ? ' ' . $words[$ones] : '');
            }
            $paisaString = ' and ' . $paisaWords . ' Paisa';
        }

        return trim($takaString) . ' Taka Only' . $paisaString;
    }
}
