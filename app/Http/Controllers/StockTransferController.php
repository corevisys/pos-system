<?php

namespace App\Http\Controllers;

use App\Models\DbItem;
use App\Models\DbItemSerial;
use App\Models\DbStockTransfer;
use App\Models\DbStockTransferItems;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        // D1: route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_view')) {
            abort(403, 'Unauthorized access to view stock transfers.');
        }

        // C1: store-scoped base query. delete_bit=0 excludes soft-deleted
        // transfers (spec-compliant soft-delete, same as every other module).
        // F4/N+1: `items` is eager-loaded because the page computes per-row item
        // aggregates (line count, total qty) AND the consumed-badge loop below
        // reads $t->items per transfer — a lazy load there would be one query
        // per row. Mirrors the adjustment list's with([..., 'items']).
        $query = DbStockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator', 'items'])
            ->where('store_id', current_store_id())
            ->where('delete_bit', 0);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('fromWarehouse', fn ($sq) => $sq->where('warehouse_name', 'like', "%{$search}%"))
                    ->orWhereHas('toWarehouse', fn ($sq) => $sq->where('warehouse_name', 'like', "%{$search}%"));
            });
        }

        // From / To warehouse filters (Phase E wiring; harmless if absent).
        if ($request->filled('warehouse_from')) {
            $query->where('warehouse_from', $request->warehouse_from);
        }
        if ($request->filled('warehouse_to')) {
            $query->where('warehouse_to', $request->warehouse_to);
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50], true) ? (int) $request->input('per_page', 10) : 10;

        // E1: CSV/Excel + print/PDF export of the current filtered set (mirrors the
        // established pattern across every redesigned list page in this project).
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $exportTransfers = $query->latest('id')->get();
            $filename = 'stock_transfers_' . date('Y_m_d_His') . '.csv';

            return response()->stream(function () use ($exportTransfers) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
                fputcsv($handle, ['Transfer Date', 'Reference No', 'From Warehouse', 'To Warehouse', 'Items', 'Total Qty', 'Note', 'Created By']);

                foreach ($exportTransfers as $tr) {
                    fputcsv($handle, [
                        $tr->transfer_date,
                        $tr->reference_no ?? '',
                        $tr->fromWarehouse->warehouse_name ?? '',
                        $tr->toWarehouse->warehouse_name ?? '',
                        $tr->items->count(),
                        number_format((float) $tr->items->sum('transfer_qty'), 2, '.', ''),
                        $tr->note ?? '',
                        $tr->creator->name ?? 'System',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $exportTransfers = $query->latest('id')->get();
            return view('module.stock.transfer_list_print', ['transfers' => $exportTransfers]);
        }

        $transfers = $query->latest()->paginate($perPage)->withQueryString();

        // F4: surface whether any of this transfer's destination stock has since
        // been consumed (sold/moved) — a single aggregate query over the page's
        // transfers so the list can show a "partially consumed" badge without a
        // per-row N+1. A transfer is flagged consumed when any of its lines'
        // destination available_qty is below the original transferred quantity
        // (the exact condition Phase A's delete/update guards block on).
        $consumedFlags = [];
        $pageTransfers = $transfers->getCollection();
        if ($pageTransfers->isNotEmpty()) {
            $destPairs = $pageTransfers->flatMap(fn ($t) => $t->items->map(
                fn ($i) => ['wh' => (int) $t->warehouse_to, 'item' => (int) $i->item_id, 'qty' => (float) $i->transfer_qty]
            ));

            if ($destPairs->isNotEmpty()) {
                $destStocks = DbWarehouseItem::where('store_id', current_store_id())
                    ->whereIn('warehouse_id', $destPairs->pluck('wh')->unique()->all())
                    ->whereIn('item_id', $destPairs->pluck('item')->unique()->all())
                    ->get()
                    ->keyBy(fn ($r) => $r->warehouse_id . ':' . $r->item_id);

                foreach ($pageTransfers as $t) {
                    $consumed = $t->items->contains(function ($i) use ($destStocks, $t) {
                        $key = (int) $t->warehouse_to . ':' . (int) $i->item_id;
                        $available = isset($destStocks[$key]) ? (float) $destStocks[$key]->available_qty : 0.0;
                        return $available < (float) $i->transfer_qty;
                    });
                    $consumedFlags[$t->id] = $consumed;
                }
            }
        }

        // C1: store-scoped warehouses for the filter dropdowns.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();

        return view('module.stock.transfer_list', compact('transfers', 'warehouses', 'consumedFlags'));
    }

    public function create()
    {
        // D1: route-level permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_add')) {
            abort(403, 'Unauthorized access to create stock transfers.');
        }

        // C1: store-scoped warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();
        return view('module.stock.create_transfer', compact('warehouses'));
    }

    public function store(Request $request)
    {
        // D1: route-level permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_add')) {
            abort(403, 'Unauthorized access to create stock transfers.');
        }

        $request->validate([
            'warehouse_from' => 'required|exists:db_warehouse,id',
            'warehouse_to' => 'required|exists:db_warehouse,id|different:warehouse_from',
            'transfer_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:db_items,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        try {
            DB::beginTransaction();

            $store_id = current_store_id();

            // C1: the two warehouses must belong to the current store.
            $validWarehouses = DbWarehouse::where('store_id', $store_id)
                ->whereIn('id', [$request->warehouse_from, $request->warehouse_to])
                ->pluck('id')
                ->all();
            if (count($validWarehouses) !== 2) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'One or both selected warehouses do not belong to your store.'], 422);
            }

            $transfer = DbStockTransfer::create([
                'store_id' => $store_id,
                'warehouse_from' => $request->warehouse_from,
                'warehouse_to' => $request->warehouse_to,
                'reference_no' => $request->reference_no ?? ('TR-' . date('YmdHis')),
                'transfer_date' => $request->transfer_date,
                'note' => $request->note,
                'created_by' => Auth::id(),
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'system_ip' => $request->ip(),
                'system_name' => gethostname(),
                'status' => 1,
                'delete_bit' => 0,
            ]);

            foreach ($request->items as $itemData) {
                $qty = $itemData['quantity'];
                
                DbStockTransferItems::create([
                    'stocktransfer_id' => $transfer->id,
                    'store_id' => $store_id,
                    'warehouse_from' => $request->warehouse_from,
                    'warehouse_to' => $request->warehouse_to,
                    'item_id' => $itemData['item_id'],
                    'transfer_qty' => $qty,
                ]);

                // 1. Decrease from Source Warehouse (store-scoped)
                $sourceWhItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_from)
                    ->where('item_id', $itemData['item_id'])
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();

                if (!$sourceWhItem || $sourceWhItem->available_qty < $qty) {
                    throw new \Exception("Insufficient stock for item ID: {$itemData['item_id']} in source warehouse.");
                }
                $sourceWhItem->decrement('available_qty', $qty);

                // 2. Increase in Destination Warehouse (store-scoped)
                $destWhItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_to)
                    ->where('item_id', $itemData['item_id'])
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();

                if ($destWhItem) {
                    $destWhItem->increment('available_qty', $qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => $store_id,
                        'warehouse_id' => $request->warehouse_to,
                        'item_id' => $itemData['item_id'],
                        'available_qty' => $qty,
                    ]);
                }

                // 3. Handle Serials — only move still-available, unsold serials that
                // actually sit in the source warehouse for this store (never relocate
                // a sold unit, never cross-store).
                if (!empty($itemData['serials']) && is_array($itemData['serials'])) {
                    foreach ($itemData['serials'] as $serialNum) {
                        if (!empty($serialNum)) {
                            $serial = DbItemSerial::where('item_id', $itemData['item_id'])
                                ->where('serial_number', $serialNum)
                                ->where('warehouse_id', $request->warehouse_from)
                                ->where('store_id', $store_id)
                                ->where('status', 0)
                                ->whereNull('sale_id')
                                ->first();
                            
                            if ($serial) {
                                $serial->update([
                                    'warehouse_id' => $request->warehouse_to,
                                    'stocktransfer_id' => $transfer->id
                                ]);
                            }
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock Transfer created successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Transfer Create Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        // D1: route-level permission gate (edit).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_edit')) {
            abort(403, 'Unauthorized access to edit stock transfers.');
        }

        // C1: store-scoped single-record lookup (IDOR protection) + store-scoped
        // warehouse dropdown. delete_bit=0: a soft-deleted transfer is not editable.
        $transfer = DbStockTransfer::with(['items.item', 'fromWarehouse', 'toWarehouse'])
            ->where('store_id', current_store_id())
            ->where('delete_bit', 0)
            ->findOrFail($id);
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();

        $transferItems = $transfer->items->map(function ($item) {
            $serials = DbItemSerial::where('stocktransfer_id', $item->stocktransfer_id)
                ->where('item_id', $item->item_id)
                ->pluck('serial_number')
                ->toArray();

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item->item_name,
                'item_code' => $item->item->item_code,
                'quantity' => $item->transfer_qty,
                'stock' => $item->item->stock ?? 0,
                'is_serialized' => (int)($item->item->is_serialized ?? 0),
                'serials' => $serials
            ];
        });

        return view('module.stock.edit_transfer', compact('transfer', 'warehouses', 'transferItems'));
    }

    public function update(Request $request, $id)
    {
        // D1: route-level permission gate (edit).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_edit')) {
            abort(403, 'Unauthorized access to edit stock transfers.');
        }

        // Similar to store but with REVERT logic
        $request->validate([
            'warehouse_from' => 'required|exists:db_warehouse,id',
            'warehouse_to' => 'required|exists:db_warehouse,id|different:warehouse_from',
            'transfer_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:db_items,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        try {
            DB::beginTransaction();

            // C-scope + A3-style guard: lock the transfer row first (store-scoped),
            // so concurrent edit+edit / edit+delete on the same transfer serialize
            // instead of both running a revert. delete_bit=0 excludes soft-deleted
            // transfers from being edited.
            $storeId = current_store_id();
            $transfer = DbStockTransfer::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->lockForUpdate()
                ->first();

            if (!$transfer) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Transfer not found or already deleted.'], 404);
            }
            $store_id = $transfer->store_id;

            // Item 2: both warehouses must belong to the current store (mirrors the
            // membership check store()/create() already perform). Validation above
            // only checks exists:db_warehouse,id — a cross-store warehouse id would
            // otherwise let this update write to another store's DbWarehouseItem.
            $validWarehouses = DbWarehouse::where('store_id', $storeId)
                ->whereIn('id', [$request->warehouse_from, $request->warehouse_to])
                ->pluck('id')
                ->all();
            if (count($validWarehouses) !== 2) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'One or both selected warehouses do not belong to your store.'], 422);
            }

            // REVERT logic (A6: every warehouse-item read is locked and store-scoped)
            $oldItems = DbStockTransferItems::where('stocktransfer_id', $id)->get();
            foreach ($oldItems as $oldItem) {
                // Return to Source (locked)
                $srcWhItem = DbWarehouseItem::where('warehouse_id', $transfer->warehouse_from)
                    ->where('item_id', $oldItem->item_id)
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();
                if ($srcWhItem) {
                    $srcWhItem->increment('available_qty', $oldItem->transfer_qty);
                }

                // Remove from Destination (locked) — same downstream-consumption
                // guard as destroy(): if the destination no longer holds the qty
                // (stock sold/moved since the transfer), block the edit entirely
                // rather than driving dest negative / creating phantom source.
                $destWhItem = DbWarehouseItem::where('warehouse_id', $transfer->warehouse_to)
                    ->where('item_id', $oldItem->item_id)
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();
                $destAvailable = $destWhItem ? (float) $destWhItem->available_qty : 0.0;
                if ($destAvailable < (float) $oldItem->transfer_qty) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This transfer cannot be edited — some of the transferred stock has already been sold or moved from the destination warehouse.',
                    ], 422);
                }
                if ($destWhItem) {
                    $destWhItem->decrement('available_qty', $oldItem->transfer_qty);
                }

                // Revert Serials — only still-available, unsold, store-scoped rows.
                DbItemSerial::where('stocktransfer_id', $id)
                    ->where('item_id', $oldItem->item_id)
                    ->where('store_id', $store_id)
                    ->where('status', 0)
                    ->whereNull('sale_id')
                    ->update([
                        'warehouse_id' => $transfer->warehouse_from,
                        'stocktransfer_id' => null
                    ]);
            }

            // Cleanup old items
            DbStockTransferItems::where('stocktransfer_id', $id)->delete();

            // Apply NEW changes
            $transfer->update([
                'warehouse_from' => $request->warehouse_from,
                'warehouse_to' => $request->warehouse_to,
                'reference_no' => $request->reference_no,
                'transfer_date' => $request->transfer_date,
                'note' => $request->note,
            ]);

            foreach ($request->items as $itemData) {
                $qty = $itemData['quantity'];
                
                DbStockTransferItems::create([
                    'stocktransfer_id' => $transfer->id,
                    'store_id' => $store_id,
                    'warehouse_from' => $request->warehouse_from,
                    'warehouse_to' => $request->warehouse_to,
                    'item_id' => $itemData['item_id'],
                    'transfer_qty' => $qty,
                ]);

                // Decrease from new Source (A5: same guard as store() — lock, then
                // throw if the new source lacks stock, instead of going negative)
                $sourceWhItemLock = DbWarehouseItem::where('warehouse_id', $request->warehouse_from)
                    ->where('item_id', $itemData['item_id'])
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();
                if (!$sourceWhItemLock || $sourceWhItemLock->available_qty < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for item ID: {$itemData['item_id']} in source warehouse.",
                    ], 422);
                }
                $sourceWhItemLock->decrement('available_qty', $qty);

                // Increase in new Destination (Item 2: store-scoped lock + write —
                // the revert path above and destroy() already scope by store_id;
                // this re-apply step was the remaining cross-store write hole).
                $destWhItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_to)
                    ->where('item_id', $itemData['item_id'])
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();

                if ($destWhItem) {
                    $destWhItem->increment('available_qty', $qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => $store_id,
                        'warehouse_id' => $request->warehouse_to,
                        'item_id' => $itemData['item_id'],
                        'available_qty' => $qty,
                    ]);
                }

                // Handle Serials (A7: scope the match by source warehouse, status=0
                // Available, and store_id — never re-point a sold serial or one that
                // belongs to an unrelated warehouse)
                if (!empty($itemData['serials']) && is_array($itemData['serials'])) {
                    foreach ($itemData['serials'] as $serialNum) {
                        if (!empty($serialNum)) {
                            DbItemSerial::where('item_id', $itemData['item_id'])
                                ->where('serial_number', $serialNum)
                                ->where('warehouse_id', $request->warehouse_from)
                                ->where('store_id', $store_id)
                                ->where('status', 0)
                                ->whereNull('sale_id')
                                ->update([
                                    'warehouse_id' => $request->warehouse_to,
                                    'stocktransfer_id' => $transfer->id
                                ]);
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock Transfer updated successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Transfer Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        // D1: route-level permission gate (delete).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_delete')) {
            abort(403, 'Unauthorized access to delete stock transfers.');
        }

        try {
            DB::beginTransaction();

            $storeId = current_store_id();

            // A3: ATOMIC conditional delete_bit transition (0 -> 1), store-scoped —
            // the same pattern used by every other module (Account/Deposit/Cash
            // Reconciliation/Supplier). Two concurrent destroy() calls both execute
            // this UPDATE; the database guarantees only ONE of them affects a row
            // (affected = 1), the loser gets affected = 0 deterministically on every
            // engine (MySQL AND SQLite — unlike lockForUpdate(), which is a no-op
            // under SQLite). This is the double-delete race guard.
            $affected = DbStockTransfer::where('id', $id)
                ->where('store_id', $storeId)
                ->where('delete_bit', 0)
                ->update(['delete_bit' => 1]);

            if ($affected !== 1) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This transfer could not be found or has already been deleted.',
                ], 404);
            }

            // Load the transfer row for its warehouse ids (still store-scoped).
            $transfer = DbStockTransfer::where('id', $id)
                ->where('store_id', $storeId)
                ->first();

            if (!$transfer) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This transfer could not be found or has already been deleted.',
                ], 404);
            }

            $items = DbStockTransferItems::where('stocktransfer_id', $id)->get();

            // ── PRE-FLIGHT CHECKS (all-or-nothing — no partial reversal) ──
            foreach ($items as $item) {
                // A1: lock the destination warehouse-item row and confirm it still
                // holds at least the original transferred quantity. If part of the
                // transfer was sold/moved downstream, deleting would decrement dest a
                // second time (→ negative) while incrementing source for units that
                // physically left (→ phantom stock). Block the whole delete instead.
                $destWhItem = DbWarehouseItem::where('warehouse_id', $transfer->warehouse_to)
                    ->where('item_id', $item->item_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                $destAvailable = $destWhItem ? (float) $destWhItem->available_qty : 0.0;
                if ($destAvailable < (float) $item->transfer_qty) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This transfer cannot be deleted — some of the transferred stock has already been sold or moved from the destination warehouse.',
                    ], 422);
                }

                // A2: if ANY serial tied to this transfer (for this item) has since
                // been sold (status=1 or sale_id set), block deletion. This is the
                // stricter of the two checks (A1 quantity vs. A2 per-serial): block
                // if EITHER fails, per the audit's default.
                $soldSerials = DbItemSerial::where('stocktransfer_id', $id)
                    ->where('item_id', $item->item_id)
                    ->where('store_id', $storeId)
                    ->where(function ($q) {
                        $q->where('status', 1)->orWhereNotNull('sale_id');
                    })
                    ->count();

                if ($soldSerials > 0) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This transfer cannot be deleted — one or more of the transferred serial numbers have already been sold. Sold inventory cannot be reverted.',
                    ], 422);
                }
            }

            // ── ALL CHECKS PASSED — reverse the movement ──
            foreach ($items as $item) {
                // Reverse source: put the qty back (locked read, store-scoped).
                $srcWhItem = DbWarehouseItem::where('warehouse_id', $transfer->warehouse_from)
                    ->where('item_id', $item->item_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();
                if ($srcWhItem) {
                    $srcWhItem->increment('available_qty', $item->transfer_qty);
                }

                // Reverse destination: take the qty back (validated above).
                $destWhItem = DbWarehouseItem::where('warehouse_id', $transfer->warehouse_to)
                    ->where('item_id', $item->item_id)
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();
                if ($destWhItem) {
                    $destWhItem->decrement('available_qty', $item->transfer_qty);
                }

                // Revert serials — only still-available, unsold rows (the A2 check
                // above guarantees none are sold, this is belt-and-braces) and only
                // for THIS store.
                DbItemSerial::where('stocktransfer_id', $id)
                    ->where('item_id', $item->item_id)
                    ->where('store_id', $storeId)
                    ->where('status', 0)
                    ->whereNull('sale_id')
                    ->update([
                        'warehouse_id' => $transfer->warehouse_from,
                        'stocktransfer_id' => null,
                    ]);
            }

            // Remove the line items explicitly (they are FK-cascade-deleted only on
            // hard delete; under soft-delete they would otherwise linger orphaned
            // on the retained row). update() does the same cleanup.
            DbStockTransferItems::where('stocktransfer_id', $id)->delete();

            // Keep the row (delete_bit=1 is the soft-delete flag every list/edit
            // query now filters on), so the delete_bit audit trail survives. The
            // stock movement and serials have already been reversed above.
            $transfer->update(['delete_bit' => 1]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock Transfer deleted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Transfer Delete Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete transfer: ' . $e->getMessage()], 500);
        }
    }

    public function searchItems(Request $request)
    {
        // D1: route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('stock_transfer_view')) {
            return response()->json([], 403);
        }

        $query = $request->get('query');
        $warehouse_id = $request->get('warehouse_id');
        $storeId = current_store_id();
        
        // C1: store-scoped item search — a Store-2 user never sees Store-1 items.
        $items = DbItem::with('warehouseItems')
                    ->where('store_id', $storeId)
                    ->where(function($q) use ($query) {
                        $q->where('item_name', 'LIKE', "%{$query}%")
                          ->orWhere('item_code', 'LIKE', "%{$query}%");
                    })
                    ->select('id', 'item_name', 'item_code', 'stock', 'is_serialized')
                    ->limit(10)
                    ->get();
                    
        // Phase 4.2.2 fix — resolve `stock` through the SINGLE canonical rule
        // (DbItem::availableStock). The previous `$whItem ? $whItem->available_qty : 0`
        // fallback showed 0 for warehouse-less items (should be db_items.stock) and
        // bypassed the canonical method. With no warehouse_id, availableStock(null)
        // returns the store-wide aggregate — identical to the raw db_items.stock already
        // shown, so the no-warehouse path is unchanged.
        $items->each(function ($item) use ($warehouse_id) {
            $item->stock = $item->availableStock($warehouse_id ?: null);
        });
                    
        return response()->json($items);
    }
}
