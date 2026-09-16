<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateSerialNumberException;
use App\Models\DbStockAdjustment;
use App\Models\DbStockAdjustmentItems;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\DbWarehouseItem;
use App\Models\DbItemSerial;
use App\Services\ItemSerialValidationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\SMS\Services\SmsTriggerService;

class StockAdjustmentController extends Controller
{
    protected $smsTriggerService;

    public function __construct(SmsTriggerService $smsTriggerService)
    {
        $this->smsTriggerService = $smsTriggerService;
    }

    public function index(Request $request)
    {
        // D1: route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_view')) {
            abort(403, 'Unauthorized access to view stock adjustments.');
        }

        // C1: store-scoped base query. items is eager-loaded because the list page
        // computes per-row aggregates (net qty badge, positive/negative stats) — a
        // lazy per-row load would be an N+1.
        $query = DbStockAdjustment::with(['warehouse', 'user', 'items'])
            ->where('store_id', current_store_id());

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhereHas('warehouse', function($sq) use ($search) {
                      $sq->where('warehouse_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50], true) ? (int) $request->input('per_page', 10) : 10;

        // E1/E2: CSV/Excel + print/PDF export of the current filtered set (mirrors
        // the established pattern across every redesigned list page).
        if (in_array($request->query('export'), ['csv', 'excel'], true)) {
            $exportAdjustments = $query->latest('id')->get();
            $filename = 'stock_adjustments_' . date('Y_m_d_His') . '.csv';

            return response()->stream(function () use ($exportAdjustments) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
                fputcsv($handle, ['Adjustment Date', 'Reference No', 'Warehouse', 'Created By', 'Reason / Note']);

                foreach ($exportAdjustments as $adj) {
                    fputcsv($handle, [
                        $adj->adjustment_date,
                        $adj->reference_no ?? '',
                        $adj->warehouse->warehouse_name ?? '',
                        $adj->user->name ?? 'System',
                        $adj->adjustment_note ?? '',
                    ]);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        if (in_array($request->query('export'), ['pdf', 'print'], true)) {
            $exportAdjustments = $query->latest('id')->get();
            return view('module.stock.adjustment_list_print', ['adjustments' => $exportAdjustments]);
        }

        $adjustments = $query->latest()->paginate($perPage)->withQueryString();
        // C1: store-scoped warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();

        return view('module.stock.adjustment_list', compact('adjustments', 'warehouses'));
    }

    public function create()
    {
        // D1: route-level permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_add')) {
            abort(403, 'Unauthorized access to create stock adjustments.');
        }

        // C1: store-scoped warehouse dropdown.
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();
        return view('module.stock.create_adjustment', compact('warehouses'));
    }

    public function store(Request $request)
    {
        // D1: route-level permission gate (add).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_add')) {
            abort(403, 'Unauthorized access to create stock adjustments.');
        }

        Log::info('Stock Adjustment Store Request:', $request->all());

        $request->validate([
            'warehouse_id' => 'required|exists:db_warehouse,id',
            'adjustment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:db_items,id',
            'items.*.quantity' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $store_id = current_store_id();

            // C1: the selected warehouse must belong to the current store.
            $warehouse = DbWarehouse::where('store_id', $store_id)->find($request->warehouse_id);
            if (!$warehouse) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'The selected warehouse does not belong to your store.'], 422);
            }

            // C1: every adjusted item must belong to the current store.
            foreach ($request->items as $itemData) {
                $itemExists = DbItem::where('store_id', $store_id)->where('id', $itemData['item_id'])->exists();
                if (!$itemExists) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Item ID {$itemData['item_id']} does not belong to your store."], 422);
                }
            }

            // Per-item serial-number uniqueness check before ANY rows are written.
            // Serialized adjustments can register new serials for an existing item,
            // so a serial already registered for that item (by any entry point) or
            // typed twice within this submission is rejected cleanly.
            app(ItemSerialValidationService::class)->validateSerialsForAdjustmentLines($request->items);

            $adjustment = DbStockAdjustment::create([
                'store_id' => $store_id,
                'warehouse_id' => $request->warehouse_id,
                'reference_no' => $request->reference_no ?: 'ADJ-' . strtoupper(uniqid()),
                'adjustment_date' => $request->adjustment_date,
                'adjustment_note' => $request->adjustment_note,
                'created_by' => auth()->id(),
                'created_date' => now()->toDateString(),
                'created_time' => now()->toTimeString(),
                'system_ip' => $request->ip(),
                'system_name' => gethostname(),
                'status' => 1,
            ]);

            foreach ($request->items as $itemData) {
                $qty = $itemData['quantity'];
                
                DbStockAdjustmentItems::create([
                    'store_id' => $store_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'adjustment_qty' => $qty,
                    'description' => $itemData['description'] ?? null,
                ]);

                // Update Item Stock (Global)
                $item = DbItem::find($itemData['item_id']);
                if ($item) {
                    $item->increment('stock', $qty);
                }

                // Update Warehouse Item Stock (B1: lockForUpdate-guarded select-
                // then-create with a unique-violation retry — replaces the old
                // first()-then-create() branch which could race two concurrent
                // adjustments for a brand-new item+warehouse into the
                // uq_warehouse_item unique index and surface a generic 500. The
                // lock serializes the create; if the row still appears between the
                // read and the create, the unique-index violation is caught (by
                // SQLSTATE 23000 and/or the uq_warehouse_item constraint name —
                // portably across MySQL AND SQLite) and retried as an increment,
                // exactly the AcAccount first-use pattern already in this project.
                // NOTE: this is NOT updateOrCreate() — there is no such call; it is
                // lockForUpdate()+first()+create()+catch-and-retry-as-increment.
                $whItem = DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                    ->where('item_id', $itemData['item_id'])
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();

                if ($whItem) {
                    $whItem->increment('available_qty', $qty);
                } else {
                    try {
                        DbWarehouseItem::create([
                            'store_id' => $store_id,
                            'warehouse_id' => $adjustment->warehouse_id,
                            'item_id' => $itemData['item_id'],
                            'available_qty' => $qty,
                        ]);
                    } catch (QueryException $e) {
                        // Lost the create race — the row now exists; increment it.
                        if ($this->isWarehouseItemUniqueViolation($e)) {
                            DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                                ->where('item_id', $itemData['item_id'])
                                ->where('store_id', $store_id)
                                ->increment('available_qty', $qty);
                        } else {
                            throw $e;
                        }
                    }
                }

                // Handle Serials if present
                if (!empty($itemData['serials']) && is_array($itemData['serials'])) {
                    foreach ($itemData['serials'] as $serial) {
                        if (!empty($serial)) {
                             DbItemSerial::updateOrCreate(
                                [
                                    'item_id' => $itemData['item_id'],
                                    'serial_number' => $serial,
                                ],
                                [
                                    'store_id' => $store_id,
                                    'warehouse_id' => $adjustment->warehouse_id,
                                    'adjustment_id' => $adjustment->id,
                                    'status' => 0, // Available
                                    'source' => 'stock_adjustment',
                                ]
                            );
                        }
                    }
                }
            }

            DB::commit();

            // Trigger SMS notifications for each adjusted item
            foreach ($adjustment->items as $adjItem) {
                $this->smsTriggerService->trigger('StockAdjustmentAlert', $adjItem);
            }

            return response()->json(['success' => true, 'message' => 'Stock Adjustment created successfully!']);

        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            // Clean per-serial message instead of a raw SQL exception.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            DB::rollBack();
            // Defense-in-depth backstop for a concurrent same item+serial race.
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            if ($msg) {
                Log::warning('Stock Adjustment unique-constraint backstop triggered: ' . $msg);
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            Log::error('Stock Adjustment Create Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create adjustment: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Adjustment Create Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create adjustment: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        // D1: route-level permission gate (edit).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_edit')) {
            abort(403, 'Unauthorized access to edit stock adjustments.');
        }

        // C1: store-scoped single-record lookup (IDOR protection) + store-scoped
        // warehouse dropdown.
        $adjustment = DbStockAdjustment::with(['items.item', 'warehouse'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);
        $warehouses = DbWarehouse::where('store_id', current_store_id())->where('status', 1)->get();

        $adjustmentItems = $adjustment->items->map(function ($item) {
            $serials = DbItemSerial::where('adjustment_id', $item->adjustment_id)
                ->where('item_id', $item->item_id)
                ->pluck('serial_number')
                ->toArray();

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item->item_name,
                'item_code' => $item->item->item_code,
                'quantity' => $item->adjustment_qty,
                'stock' => $item->item->stock ?? 0,
                'is_serialized' => (int)($item->item->is_serialized ?? 0),
                'serials' => $serials
            ];
        });

        return view('module.stock.edit_adjustment', compact('adjustment', 'warehouses', 'adjustmentItems'));
    }

    public function update(Request $request, $id)
    {
        // D1: route-level permission gate (edit).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_edit')) {
            abort(403, 'Unauthorized access to edit stock adjustments.');
        }

        $request->validate([
            'warehouse_id' => 'required|exists:db_warehouse,id',
            'adjustment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:db_items,id',
            'items.*.quantity' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // C1: store-scoped single-record lookup (IDOR protection).
            $store_id = current_store_id();
            $adjustment = DbStockAdjustment::where('id', $id)
                ->where('store_id', $store_id)
                ->lockForUpdate()
                ->first();
            if (!$adjustment) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Adjustment not found or already deleted.'], 404);
            }

            // C1: selected warehouse + items must belong to the current store.
            $warehouse = DbWarehouse::where('store_id', $store_id)->find($request->warehouse_id);
            if (!$warehouse) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'The selected warehouse does not belong to your store.'], 422);
            }
            foreach ($request->items as $itemData) {
                $itemExists = DbItem::where('store_id', $store_id)->where('id', $itemData['item_id'])->exists();
                if (!$itemExists) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Item ID {$itemData['item_id']} does not belong to your store."], 422);
                }
            }

            // Revert stock changes from old items (store-scoped reads) — shared
            // with destroy() so the revert math can never diverge between the two.
            $oldItems = DbStockAdjustmentItems::where('adjustment_id', $id)->get();
            $this->revertAdjustmentStock($oldItems, $adjustment->warehouse_id, $store_id, $id);

            // Update Adjustment record
            $adjustment->warehouse_id = $request->warehouse_id;
            $adjustment->adjustment_date = $request->adjustment_date;
            $adjustment->reference_no = $request->reference_no ?: $adjustment->reference_no;
            $adjustment->adjustment_note = $request->adjustment_note;
            $adjustment->system_ip = $request->ip();
            $adjustment->system_name = gethostname();
            $adjustment->save();

            DbStockAdjustmentItems::where('adjustment_id', $id)->delete();

            // Per-item serial-number uniqueness check AFTER the revert deleted this
            // adjustment's own serial rows — re-adding them is not flagged, but a
            // serial already registered for the same item elsewhere (or typed twice
            // within this submission) is rejected cleanly before anything is written.
            app(ItemSerialValidationService::class)->validateSerialsForAdjustmentLines($request->items);

            foreach ($request->items as $itemData) {
                $qty = $itemData['quantity'];
                
                DbStockAdjustmentItems::create([
                    'store_id' => $store_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'adjustment_qty' => $qty,
                    'description' => $itemData['description'] ?? null,
                ]);

                // Update Item Stock (New)
                $item = DbItem::find($itemData['item_id']);
                if ($item) {
                    $item->increment('stock', $qty);
                }

                // Update Warehouse Item Stock (New) — B1: lockForUpdate-guarded
                // select-then-create with unique-violation retry, same as store().
                $whItem = DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                    ->where('item_id', $itemData['item_id'])
                    ->where('store_id', $store_id)
                    ->lockForUpdate()
                    ->first();

                if ($whItem) {
                    $whItem->increment('available_qty', $qty);
                } else {
                    try {
                        DbWarehouseItem::create([
                            'store_id' => $store_id,
                            'warehouse_id' => $adjustment->warehouse_id,
                            'item_id' => $itemData['item_id'],
                            'available_qty' => $qty,
                        ]);
                    } catch (QueryException $e) {
                        // Same portable detection as store(): SQLSTATE 23000 or the
                        // uq_warehouse_item constraint name (works on MySQL AND SQLite).
                        if ($this->isWarehouseItemUniqueViolation($e)) {
                            DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                                ->where('item_id', $itemData['item_id'])
                                ->where('store_id', $store_id)
                                ->increment('available_qty', $qty);
                        } else {
                            throw $e;
                        }
                    }
                }

                // Handle Serials if present
                if (!empty($itemData['serials']) && is_array($itemData['serials'])) {
                    foreach ($itemData['serials'] as $serial) {
                        if (!empty($serial)) {
                            DbItemSerial::updateOrCreate(
                                [
                                    'item_id' => $itemData['item_id'],
                                    'serial_number' => $serial,
                                ],
                                [
                                    'store_id' => $store_id,
                                    'warehouse_id' => $adjustment->warehouse_id,
                                    'adjustment_id' => $adjustment->id,
                                    'status' => 0, // Available
                                    'source' => 'stock_adjustment',
                                ]
                            );
                        }
                    }
                }
            }

            DB::commit();

            // Trigger SMS notifications for updated adjusted items
            $adjustment->load('items');
            foreach ($adjustment->items as $adjItem) {
                $this->smsTriggerService->trigger('StockAdjustmentAlert', $adjItem);
            }

            return response()->json(['success' => true, 'message' => 'Stock Adjustment updated successfully!']);

        } catch (DuplicateSerialNumberException $e) {
            DB::rollBack();
            // Clean per-serial message instead of a raw SQL exception.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            DB::rollBack();
            // Defense-in-depth backstop for a concurrent same item+serial race.
            $msg = app(ItemSerialValidationService::class)->translateDuplicateSerialQueryException($e);
            if ($msg) {
                Log::warning('Stock Adjustment unique-constraint backstop triggered: ' . $msg);
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            Log::error('Stock Adjustment Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update adjustment: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Adjustment Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update adjustment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * B2: read-only detail view for an adjustment (the previously-dead "View"
     * link now targets this route).
     */
    public function show($id)
    {
        // D1: route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_view')) {
            abort(403, 'Unauthorized access to view stock adjustments.');
        }

        $adjustment = DbStockAdjustment::with(['items.item', 'warehouse', 'user'])
            ->where('store_id', current_store_id())
            ->findOrFail($id);

        $adjustmentItems = $adjustment->items->map(function ($item) {
            $serials = DbItemSerial::where('adjustment_id', $item->adjustment_id)
                ->where('item_id', $item->item_id)
                ->pluck('serial_number')
                ->toArray();

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item->item_name,
                'item_code' => $item->item->item_code,
                'quantity' => $item->adjustment_qty,
                'description' => $item->description,
                'serials' => $serials,
            ];
        });

        return view('module.stock.show_adjustment', compact('adjustment', 'adjustmentItems'));
    }

    /**
     * B2: reverse an adjustment's stock delta and delete the record.
     *
     * Mirrors update()'s already-proven revert-first logic (reused rather than
     * re-implemented), wrapped in a transaction with the transfer row locked
     * (double-submit guard) and store-scoped. Guards before reversing:
     *   - would-go-negative (positive adjustment whose stock was consumed since)
     *   - sold serials registered by this adjustment (A2 principle: never destroy
     *     a serial record that a sale now references)
     * An all-or-nothing block is returned on either guard — no partial reversal.
     */
    public function destroy($id)
    {
        // D1: route-level permission gate (delete).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_delete')) {
            abort(403, 'Unauthorized access to delete stock adjustments.');
        }

        try {
            DB::beginTransaction();

            $storeId = current_store_id();
            $adjustment = DbStockAdjustment::where('id', $id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$adjustment) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This adjustment could not be found or has already been deleted.',
                ], 404);
            }

            $items = DbStockAdjustmentItems::where('adjustment_id', $id)->get();

            // ── PRE-FLIGHT GUARDS (all-or-nothing) ──
            foreach ($items as $oldItem) {
                $qty = (float) $oldItem->adjustment_qty;

                // Reversing a positive adjustment decrements stock — block if the
                // current stock can't absorb it (stock consumed downstream since).
                if ($qty > 0) {
                    $whItem = DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                        ->where('item_id', $oldItem->item_id)
                        ->where('store_id', $storeId)
                        ->lockForUpdate()
                        ->first();
                    $whAvailable = $whItem ? (float) $whItem->available_qty : 0.0;
                    if ($whAvailable < $qty) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'This adjustment cannot be deleted — reversing it would drive warehouse stock negative (some of the adjusted stock has since been sold or consumed).',
                        ], 422);
                    }

                    $item = DbItem::find($oldItem->item_id);
                    if ($item && (float) $item->stock < $qty) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'This adjustment cannot be deleted — reversing it would drive the item\'s global stock negative.',
                        ], 422);
                    }
                }

                // Sold-serial guard: an adjustment-created serial that a sale now
                // references must never be deleted.
                $soldSerials = DbItemSerial::where('adjustment_id', $id)
                    ->where('item_id', $oldItem->item_id)
                    ->where('store_id', $storeId)
                    ->where(function ($q) {
                        $q->where('status', 1)->orWhereNotNull('sale_id');
                    })
                    ->count();

                if ($soldSerials > 0) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This adjustment cannot be deleted — one or more of the serial numbers it registered have already been sold.',
                    ], 422);
                }
            }

            // ── ALL CHECKS PASSED — reverse via the SAME shared revert used by
            // update() (Item 6: the revert math lives in one place) ──
            $this->revertAdjustmentStock($items, $adjustment->warehouse_id, $storeId, $id);

            $adjustment->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock Adjustment deleted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Adjustment Delete Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete adjustment: ' . $e->getMessage()], 500);
        }
    }

    public function searchItems(Request $request)
    {
        // D1: route-level permission gate (view).
        if (auth()->check() && !auth()->user()->hasPermission('stock_adjustment_view')) {
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
                          ->orWhere('item_code', 'LIKE', "%{$query}%")
                          ->orWhere('sku', 'LIKE', "%{$query}%");
                    })
                    ->select('id', 'item_name', 'item_code', 'stock', 'purchase_price', 'sales_price', 'item_image', 'is_serialized')
                    ->limit(10)
                    ->get();
                    
        // Phase 4.2.2 fix — resolve `stock` through the SINGLE canonical rule
        // (DbItem::availableStock), the same rule used at POS/Sale search and at the
        // checkout gate. The previous `$whItem ? $whItem->available_qty : 0` fallback
        // showed 0 for warehouse-less items (should be db_items.stock) and bypassed the
        // canonical method. When no warehouse_id is passed, availableStock(null) returns
        // the store-wide aggregate — identical to the raw db_items.stock already shown,
        // so the no-warehouse path is unchanged.
        $items->each(function ($item) use ($warehouse_id) {
            $item->stock = $item->availableStock($warehouse_id ?: null);
        });
                    
        return response()->json($items);
    }

    /**
     * Portable detection of a uq_warehouse_item (warehouse_id, item_id) unique
     * constraint violation across database engines.
     *
     * The prior rollout checked str_contains(strtolower($e->getMessage()), 'unique').
     * That substring exists in SQLite's message ("UNIQUE constraint failed") but
     * NOT in MySQL's ("Duplicate entry '...' for key 'db_warehouseitems.
     * uq_warehouse_item'") — so on the production engine the retry never fired and
     * a genuine concurrent collision surfaced as a generic 500. This helper is
     * robust against both formats:
     *
     *   1. SQLSTATE code 23000 — the standard integrity-constraint-violation code
     *      across MySQL, SQLite and PostgreSQL.
     *   2. The explicit constraint name 'uq_warehouse_item' in the message, which
     *      appears verbatim in BOTH engines' error text.
     *
     * A fallback to a general "unique" token is deliberately NOT included — that
     * would widen the catch to unrelated constraints; the retry must only fire for
     * THIS index.
     */
    protected function isWarehouseItemUniqueViolation(QueryException $e): bool
    {
        // Laravel wraps the underlying PDO exception; the SQLSTATE is on the wrapper
        // (->getCode() returns the SQLSTATE, e.g. '23000').
        $sqlState = (string) $e->getCode();
        if ($sqlState === '23000') {
            return true;
        }

        $previous = $e->getPrevious();
        if ($previous instanceof \PDOException) {
            $prevCode = (string) $previous->getCode();
            if ($prevCode === '23000') {
                return true;
            }
        }

        $message = strtolower($e->getMessage());
        return str_contains($message, 'uq_warehouse_item');
    }

    /**
     * Shared stock-reversal logic used by BOTH update()'s revert step and
     * destroy()'s reversal step (Item 6 — previously duplicated, which would let
     * a future fix to one silently diverge from the other).
     *
     * Reverts, per adjustment line:
     *  - global db_items.stock: decrement by adjustment_qty (store-scoped read)
     *  - warehouse available_qty: decrement by adjustment_qty (locked, store-scoped)
     *  - serial rows registered by this adjustment: delete only rows that are
     *    still Available & unsold (status=0, sale_id IS NULL) — sold ones are
     *    guarded by the caller's pre-flight check.
     *
     * NOTE: for update() this must run BEFORE the uniqueness validation re-inserts
     * this adjustment's own serials (so re-adding them is not flagged).
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $items
     */
    protected function revertAdjustmentStock($items, int $warehouseId, int $storeId, int $adjustmentId): void
    {
        foreach ($items as $oldItem) {
            $qty = (float) $oldItem->adjustment_qty;

            $item = DbItem::where('store_id', $storeId)->find($oldItem->item_id);
            if ($item) {
                $item->decrement('stock', $qty);
            }

            $whItem = DbWarehouseItem::where('warehouse_id', $warehouseId)
                ->where('item_id', $oldItem->item_id)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();
            if ($whItem) {
                $whItem->decrement('available_qty', $qty);
            }

            DbItemSerial::where('adjustment_id', $adjustmentId)
                ->where('item_id', $oldItem->item_id)
                ->where('store_id', $storeId)
                ->where('status', 0)
                ->whereNull('sale_id')
                ->delete();
        }
    }
}
