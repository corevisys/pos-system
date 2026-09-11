<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbWarehouse extends Model
{
    use HasFactory;
    use \App\Models\Traits\StoreScoped;

    protected $table = 'db_warehouse';

    protected $fillable = [
        'store_id',
        'warehouse_type',
        'warehouse_name',
        'mobile',
        'email',
        'status',
        'created_date',
        'delete_bit',
    ];

    /**
     * Get the store that owns the warehouse.
     */
    public function store()
    {
        return $this->belongsTo(DbStore::class, 'store_id');
    }

    /**
     * Get the items in this warehouse.
     */
    public function warehouseItems()
    {
        return $this->hasMany(DbWarehouseItem::class, 'warehouse_id');
    }

    /**
     * Scope a query to search warehouses (Phase 2: store-scoped).
     */
    public function scopeSearch($query, $search, ?int $storeId = null)
    {
        $query->where('warehouse_name', 'like', "%{$search}%")
            ->orWhere('mobile', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }

    /**
     * Scope a query to filter by status (Phase 2: store-scoped).
     */
    public function scopeFilterStatus($query, $status, ?int $storeId = null)
    {
        $query->where('status', $status);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
}
