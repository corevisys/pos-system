<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbWarehouse extends Model
{
    use HasFactory;

    protected $table = 'db_warehouse';

    protected $guarded = [];

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
     * Scope a query to search warehouses.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('warehouse_name', 'like', "%{$search}%")
            ->orWhere('mobile', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeFilterStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
