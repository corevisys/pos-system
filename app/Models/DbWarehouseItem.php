<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbWarehouseItem extends Model
{
    use HasFactory;

    protected $table = 'db_warehouseitems';

    protected $fillable = [
        'store_id',
        'warehouse_id',
        'item_id',
        'available_qty'
    ];

    /**
     * Get the item associated with this record.
     */
    public function item()
    {
        return $this->belongsTo(DbItem::class, 'item_id');
    }

    /**
     * Get the warehouse associated with this record.
     */
    public function warehouse()
    {
        return $this->belongsTo(DbWarehouse::class, 'warehouse_id');
    }
}
