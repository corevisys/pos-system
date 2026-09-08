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
        $query = DbStockAdjustment::with(['warehouse', 'user']);

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

        $adjustments = $query->latest()->paginate(10)->withQueryString();
        $warehouses = DbWarehouse::where('status', 1)->get();

        return view('module.stock.adjustment_list', compact('adjustments', 'warehouses'));
    }

    public function create()
    {
        $warehouses = DbWarehouse::where('status', 1)->get();
        return view('module.stock.create_adjustment', compact('warehouses'));
    }

    public function store(Request $request)
    {
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

            $store_id = auth()->user()->store_id ?: 1;

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

                // Update Warehouse Item Stock
                $warehouseItem = DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                    ->where('item_id', $itemData['item_id'])
                    ->first();

                if ($warehouseItem) {
                    $warehouseItem->increment('available_qty', $qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => $store_id,
                        'warehouse_id' => $adjustment->warehouse_id,
                        'item_id' => $itemData['item_id'],
                        'available_qty' => $qty,
                    ]);
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
        $adjustment = DbStockAdjustment::with(['items.item', 'warehouse'])->findOrFail($id);
        $warehouses = DbWarehouse::where('status', 1)->get();

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
        $request->validate([
            'warehouse_id' => 'required|exists:db_warehouse,id',
            'adjustment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:db_items,id',
            'items.*.quantity' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $adjustment = DbStockAdjustment::findOrFail($id);
            $store_id = $adjustment->store_id;

            // Revert stock changes from old items
            $oldItems = DbStockAdjustmentItems::where('adjustment_id', $id)->get();
            foreach ($oldItems as $oldItem) {
                // Revert Item Stock
                $item = DbItem::find($oldItem->item_id);
                if ($item) {
                    $item->decrement('stock', $oldItem->adjustment_qty);
                }

                // Revert Warehouse Item Stock
                $warehouseItem = DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                    ->where('item_id', $oldItem->item_id)
                    ->first();

                if ($warehouseItem) {
                    $warehouseItem->decrement('available_qty', $oldItem->adjustment_qty);
                }

                // Revert Serials
                DbItemSerial::where('adjustment_id', $id)
                    ->where('item_id', $oldItem->item_id)
                    ->delete();
            }

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

                // Update Warehouse Item Stock (New)
                $warehouseItem = DbWarehouseItem::where('warehouse_id', $adjustment->warehouse_id)
                    ->where('item_id', $itemData['item_id'])
                    ->first();

                if ($warehouseItem) {
                    $warehouseItem->increment('available_qty', $qty);
                } else {
                    DbWarehouseItem::create([
                        'store_id' => $store_id,
                        'warehouse_id' => $adjustment->warehouse_id,
                        'item_id' => $itemData['item_id'],
                        'available_qty' => $qty,
                    ]);
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

    public function searchItems(Request $request)
    {
        $query = $request->get('query');
        $warehouse_id = $request->get('warehouse_id');
        
        $items = DbItem::where(function($q) use ($query) {
                        $q->where('item_name', 'LIKE', "%{$query}%")
                          ->orWhere('item_code', 'LIKE', "%{$query}%")
                          ->orWhere('sku', 'LIKE', "%{$query}%");
                    })
                    ->select('id', 'item_name', 'item_code', 'stock', 'purchase_price', 'sales_price', 'item_image', 'is_serialized')
                    ->limit(10)
                    ->get();
                    
        foreach($items as $item) {
            if($warehouse_id) {
                $whItem = DbWarehouseItem::where('warehouse_id', $warehouse_id)
                            ->where('item_id', $item->id)
                            ->first();
                $item->stock = $whItem ? $whItem->available_qty : 0;
            }
        }
                    
        return response()->json($items);
    }
}
