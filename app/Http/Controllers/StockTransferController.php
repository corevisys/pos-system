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
    public function index()
    {
        $transfers = DbStockTransfer::with(['fromWarehouse', 'toWarehouse', 'creator'])
            ->latest()
            ->paginate(10);
        return view('module.stock.transfer_list', compact('transfers'));
    }

    public function create()
    {
        $warehouses = DbWarehouse::where('status', 1)->get();
        return view('module.stock.create_transfer', compact('warehouses'));
    }

    public function store(Request $request)
    {
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

            $store_id = Auth::user()->store_id ?? 1;

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

                // 1. Decrease from Source Warehouse
                $sourceWhItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_from)
                    ->where('item_id', $itemData['item_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$sourceWhItem || $sourceWhItem->available_qty < $qty) {
                    throw new \Exception("Insufficient stock for item ID: {$itemData['item_id']} in source warehouse.");
                }
                $sourceWhItem->decrement('available_qty', $qty);

                // 2. Increase in Destination Warehouse
                $destWhItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_to)
                    ->where('item_id', $itemData['item_id'])
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

                // 3. Handle Serials
                if (!empty($itemData['serials']) && is_array($itemData['serials'])) {
                    foreach ($itemData['serials'] as $serialNum) {
                        if (!empty($serialNum)) {
                            $serial = DbItemSerial::where('item_id', $itemData['item_id'])
                                ->where('serial_number', $serialNum)
                                ->where('warehouse_id', $request->warehouse_from)
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
        $transfer = DbStockTransfer::with(['items.item', 'fromWarehouse', 'toWarehouse'])->findOrFail($id);
        $warehouses = DbWarehouse::where('status', 1)->get();

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

            $transfer = DbStockTransfer::findOrFail($id);
            $store_id = $transfer->store_id;

            // REVERT logic
            $oldItems = DbStockTransferItems::where('stocktransfer_id', $id)->get();
            foreach ($oldItems as $oldItem) {
                // Return to Source
                DbWarehouseItem::where('warehouse_id', $transfer->warehouse_from)
                    ->where('item_id', $oldItem->item_id)
                    ->increment('available_qty', $oldItem->transfer_qty);
                
                // Remove from Destination
                DbWarehouseItem::where('warehouse_id', $transfer->warehouse_to)
                    ->where('item_id', $oldItem->item_id)
                    ->decrement('available_qty', $oldItem->transfer_qty);

                // Revert Serials
                DbItemSerial::where('stocktransfer_id', $id)
                    ->where('item_id', $oldItem->item_id)
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

                // Decrease from new Source
                $sourceWhItemLock = DbWarehouseItem::where('warehouse_id', $request->warehouse_from)
                    ->where('item_id', $itemData['item_id'])
                    ->lockForUpdate()
                    ->first();
                if ($sourceWhItemLock) {
                    $sourceWhItemLock->decrement('available_qty', $qty);
                }

                // Increase in new Destination
                $destWhItem = DbWarehouseItem::where('warehouse_id', $request->warehouse_to)
                    ->where('item_id', $itemData['item_id'])
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

                // Handle Serials
                if (!empty($itemData['serials']) && is_array($itemData['serials'])) {
                    foreach ($itemData['serials'] as $serialNum) {
                        if (!empty($serialNum)) {
                            DbItemSerial::where('item_id', $itemData['item_id'])
                                ->where('serial_number', $serialNum)
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
        try {
            DB::beginTransaction();
            $transfer = DbStockTransfer::findOrFail($id);

            // Revert stock before deleting
            $items = DbStockTransferItems::where('stocktransfer_id', $id)->get();
            foreach ($items as $item) {
                DbWarehouseItem::where('warehouse_id', $transfer->warehouse_from)
                    ->where('item_id', $item->item_id)
                    ->increment('available_qty', $item->transfer_qty);

                DbWarehouseItem::where('warehouse_id', $transfer->warehouse_to)
                    ->where('item_id', $item->item_id)
                    ->decrement('available_qty', $item->transfer_qty);

                DbItemSerial::where('stocktransfer_id', $id)
                    ->where('item_id', $item->item_id)
                    ->update([
                        'warehouse_id' => $transfer->warehouse_from,
                        'stocktransfer_id' => null
                    ]);
            }

            $transfer->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Stock Transfer deleted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function searchItems(Request $request)
    {
        $query = $request->get('query');
        $warehouse_id = $request->get('warehouse_id');
        
        $items = DbItem::where(function($q) use ($query) {
                        $q->where('item_name', 'LIKE', "%{$query}%")
                          ->orWhere('item_code', 'LIKE', "%{$query}%");
                    })
                    ->select('id', 'item_name', 'item_code', 'stock', 'is_serialized')
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
