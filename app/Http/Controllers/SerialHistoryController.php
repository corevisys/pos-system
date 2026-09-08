<?php

namespace App\Http\Controllers;

use App\Models\DbItemSerial;
use App\Models\DbItem;
use App\Models\DbSale;
use App\Models\DbPurchase;
use App\Models\DbStockAdjustment;
use App\Models\DbStockTransfer;
use App\Models\DbSalesItemReturn;
use App\Models\DbSalesReturn;
use Illuminate\Http\Request;

/**
 * Serial History — a READ-ONLY lookup page that reconstructs a serial's full
 * lifecycle from existing data (db_item_serials.purchase_id / adjustment_id /
 * stocktransfer_id / sale_id / source / status + the returned_serials JSON
 * written by SalesReturnController::store()). No write path is touched.
 */
class SerialHistoryController extends Controller
{
    /**
     * Display the serial lookup page (GET, with optional ?q= prefilled search).
     */
    public function index(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        $history = null;

        if ($query !== '') {
            $history = $this->lookup($query);
        }

        return view('module.items.serial_history', compact('query', 'history'));
    }

    /**
     * Resolve a serial_number (current store only) to its full timeline.
     *
     * Returns null when no serial matches the current store — this doubles as
     * the store-scoping gate: a Store 2 user can never look up a Store 1 serial.
     */
    public function lookup(string $query): ?array
    {
        $storeId = current_store_id();

        $serial = DbItemSerial::with('item')
            ->where('store_id', $storeId)
            ->whereRaw('LOWER(serial_number) = ?', [strtolower($query)])
            ->first();

        if (!$serial) {
            return null;
        }

        // ── Current status ────────────────────────────────────────────────
        $statusLabel = (int) $serial->status === 1 ? 'Sold' : 'Available';

        // ── How it entered stock (source + linked document) ───────────────
        $entry = ['source' => $serial->source, 'label' => null, 'url' => null];
        switch ($serial->source) {
            case 'purchase':
            case 'purchase_edit':
                if ($serial->purchase_id) {
                    $purchase = DbPurchase::find($serial->purchase_id);
                    if ($purchase) {
                        $entry['label'] = $purchase->purchase_code;
                        $entry['url'] = route('purchase.invoice', $purchase->id);
                    }
                }
                break;
            case 'stock_adjustment':
            case 'stock_adjustment_edit':
                if ($serial->adjustment_id) {
                    $adjustment = DbStockAdjustment::find($serial->adjustment_id);
                    if ($adjustment) {
                        $entry['label'] = $adjustment->reference_no ?: ('Adjustment #' . $adjustment->id);
                        $entry['url'] = route('stock.adjustment');
                    }
                }
                break;
            case 'stock_transfer':
            case 'stock_transfer_edit':
                if ($serial->stocktransfer_id) {
                    $transfer = DbStockTransfer::find($serial->stocktransfer_id);
                    if ($transfer) {
                        $entry['label'] = $transfer->reference_no ?: ('Transfer #' . $transfer->id);
                        $entry['url'] = route('stock.transfer');
                    }
                }
                break;
            default:
                // 'item_add' / 'item_edit' → entered via opening-stock serials.
                $entry['label'] = 'Opening stock';
        }

        // ── Sale / invoice linkage (same relation the invoice view uses) ──
        $sale = null;
        if ($serial->sale_id) {
            $sale = DbSale::with(['customer', 'warehouse'])
                ->where('store_id', $storeId)
                ->find($serial->sale_id);
        }

        // ── Return references (returned_serials JSON written by the return flow) ──
        $returns = [];
        $returnRows = DbSalesItemReturn::with('return')
            ->where('item_id', $serial->item_id)
            ->whereNotNull('returned_serials')
            ->get()
            ->filter(function ($rItem) use ($serial) {
                $serials = json_decode((string) $rItem->returned_serials, true);
                return is_array($serials) && in_array($serial->serial_number, $serials, true);
            });

        foreach ($returnRows as $rItem) {
            if (!$rItem->return) {
                continue;
            }
            $returns[] = [
                'return_code' => $rItem->return->return_code,
                'return_date' => $rItem->return->return_date,
                'return_qty' => $rItem->return_qty,
                'url' => route('sales.return.show', $rItem->return->id),
            ];
        }

        return [
            'serial_number' => $serial->serial_number,
            'item' => $serial->item ? [
                'id' => $serial->item->id,
                'name' => $serial->item->item_name,
                'code' => $serial->item->item_code,
            ] : null,
            'status' => (int) $serial->status,
            'status_label' => $statusLabel,
            'warehouse_id' => $serial->warehouse_id,
            'entry' => $entry,
            'sale' => $sale ? [
                'id' => $sale->id,
                'sales_code' => $sale->sales_code,
                'sales_date' => $sale->sales_date,
                'customer' => $sale->customer->customer_name ?? null,
                'url' => route('sales.invoice', $sale->id),
            ] : null,
            'returns' => $returns,
        ];
    }
}
